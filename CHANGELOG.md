# Changelog

Package: [`danipa/sdk`](https://packagist.org/packages/danipa/sdk) · Source: [github.com/Danipa/php-sdk](https://github.com/Danipa/php-sdk)

## Unreleased

## 0.4.1 — 2026-10-27

### Security

- With no transport injected, the SDK now picks a PSR-18 client that does not follow redirects: Guzzle when it is installed, else Symfony HttpClient built with `max_redirects: 0`, else `php-http/discovery` as before. Before, discovery could pick Symfony HttpClient with its default settings, even when Guzzle was installed. That client follows redirects and resent the `X-API-Key` header to the redirect target. A 3xx response now surfaces as `DanipaApiError` (#7744).
- The README now says an injected transport must not follow redirects, with a Symfony example. The SDK cannot change the redirect policy of a client you inject (#7744).
- `composer.json` now declares a conflict with `guzzlehttp/guzzle` below 7. Guzzle 6 is not a PSR-18 client, so it could not serve as the default transport (#7744).

### Fixed

- A retried request now sends the full JSON body on every attempt. Each attempt gets a fresh body stream built from the same encoded bytes, with the same `Idempotency-Key`. Before, a PSR-18 transport that consumed the body stream could send an empty body on the retry after a retryable status or a network error (Part of #7463).
- `DanipaWebhook::constructEvent()` now rejects a body whose JSON root is an array, including `[]`, with `WebhookVerificationError` ("body is not a JSON object"). An empty object `{}` is still accepted. Signature verification is unchanged. Response bodies get the same root-type check (Part of #7463).

### Changed

- An explicit `baseUrl()` is now validated at construction, like the Java SDK: it must be an absolute `http://` or `https://` URL with a host, and must not contain a query, a fragment or user-info. Anything else throws `ConfigurationError` and nothing is sent. Plain `http` stays allowed for local and test servers. The default sandbox and live URLs are unaffected (Part of #7463).

## 0.4.0 — 2026-10-26

### Added

- `WebhookEvent::$dedupeKey` (readonly `string`, set by the constructor; signature unchanged): lowercase hex SHA-256 of the verified raw body bytes, the same on every retry of a delivery. It identifies a body, not a delivery: a dashboard replay, or two distinct events with byte-identical bodies, share it, so prefer the body's own unique id where it has one. Dedupe on it, or on the body's own id. Danipa re-signs every webhook retry with a fresh timestamp, so the signature and timestamp change per attempt while the body stays the same. The docs no longer say to dedupe on the delivery id: `X-Danipa-Event` and `X-Danipa-Delivery` are unsigned and are now documented as informational only; branch on the body's own event type where present (#7614).
- `Invoice::$taxHeld` (`?bool`, last constructor parameter, default `null`): `true` on a draft held for a tax-blocked subscription period, whose tax shows as 0 until it is sent; `null` when the server omits it. Existing positional constructor calls keep working (#7615).

## 0.3.0 — 2026-10-25

### Upgrade notes (read first)

- **Authentication now works. Upgrade from every earlier release.** The previous published build (Packagist `danipa/sdk` v0.1.0) sent the API key as `Authorization: Bearer …`, which the API has never accepted, so every call failed with 401. 0.3.0 sends the key as `X-API-Key` only and never sends an `Authorization` header (sending both is rejected). Verified against sandbox on 2026-10-25 (Part of #7463).
- **Production is not live yet.** A `dk_live_…` key, or an explicit live environment, with no explicit `baseUrl()` now makes `Danipa::builder()->...->build()` throw `ConfigurationError` ("production is not live yet — use a dk_test_ key against sandbox"). This replaces a DNS error on the first call, and nothing is sent. `dk_test_…` keys still go to `https://api.sandbox.danipa.com/ms`. The live default stays `https://api.danipa.com/ms`, and a later 0.x release removes this guard when production launches. An explicit `baseUrl()` is never blocked.
- Routes the earlier build called that never existed on the server are gone; see **Removed** below.

The version jumps from 0.1.0 to 0.3.0 so all four Danipa SDKs share one version line.

### 0.3.0 readiness: the whole API-key surface (#5573)

The SDK now covers every route an API key can call (the server's `ApiKeyAuthenticationFilter` route table) and nothing
else.

#### Added

- `paymentLinks()->list($page, $size)`, `->update($id, UpdatePaymentLinkRequest)` and `->delete($id)`. `delete` returns
  a `PaymentLinkDeleteResult` that says whether the link was deleted (`204`) or, because it had payments, deactivated
  (`200 {"action":"deactivated"}`).
- `invoices()->list($status, $q, $page, $size)`, `->get($id)`, `->send($id)` and `->void($id)`.
- `balances()->list()`: `GET /v1/merchants/me/wallet/balances`, returning `list<WalletBalance>` (one per currency).
- `Page<T>` for list results (`items`, `number`, `size`, `totalElements`, `totalPages`, `hasNext()`), read from the
  Spring Data page the server writes (top-level `content` / `totalElements` / ..., and the nested `page` shape too).
- `UpdatePaymentLinkRequest`, mirroring the server DTO: a partial update, every field optional, null fields omitted.
- `Merchant::$gstHstNumber` and `Merchant::$qstNumber` (nullable), the merchant's Canadian sales-tax registrations,
  now on the server's `MerchantResponse` (#5608).
- `Environment` enum (`SANDBOX`, `LIVE`) and `DanipaBuilder::environment()`.
- Client-side checks before anything is sent: path ids must be UUIDs (the server's key route table only matches a
  UUID, so any other id came back as a misleading `403 API_KEY_SCOPE_DENIED`); `page >= 0`; `size` 1–200; the invoice
  `status` filter must be one of the server's `InvoiceStatus` values (the server answers an unknown one with a 500).
- `test/RouteContractTest.php`: the SDK's (verb, path) set must equal the server's API-key route table, and every public
  resource method must be in it, so drift fails CI.
- `examples/smoke_sandbox.php`: a live sandbox walk of every call (not run in CI).

#### Changed

- **Breaking: an API key whose prefix is neither `dk_test_` nor `dk_live_` now fails at `build()` with
  `Errors\ConfigurationError`** (an `InvalidArgumentException`) unless you pass `baseUrl()` or `environment()`. It used
  to be sent to sandbox silently. The key is never included in the message.
- `Danipa`'s public constructor takes a fourth argument, `BalancesResource $balances` (build clients with `Danipa::builder()`).
- Base-URL precedence is explicit: `baseUrl()` > `environment()` > key prefix. A blank `baseUrl()` is rejected.
- An empty request body is sent as `{}`, not `[]`.
- The payment-link and invoice `create` docs no longer say the routes are key-denied: they are on the API-key surface.
- Internal: `HttpClient::SANDBOX_BASE_URL` / `PRODUCTION_BASE_URL` are gone; use `Environment::SANDBOX->baseUrl()` /
  `Environment::LIVE->baseUrl()`.

#### Removed

- Nothing callable was removed: every existing method (`merchants()->me()`, `paymentLinks()->create()`,
  `invoices()->create()`) is on the key route table. The SDK deliberately has no `paymentLinks()->get()`, invoice update,
  PDF, stats, record-payment or bulk methods: those routes are dashboard-only and would always return `403` to a key.

### SDK phase 1

SDK phase 1: the SDK now matches what the server actually does.

#### Breaking changes

- **Auth header:** the API key is sent as `X-API-Key`, not `Authorization: Bearer`. The server never accepted a bearer
  API key, and sending both headers is rejected with `403 AMBIGUOUS_CREDENTIALS`.
- **PHP 8.2+** is required (it was 8.1).
- **Guzzle is no longer a hard dependency.** The SDK uses any PSR-18 client and PSR-17 factories, found through
  `php-http/discovery` or injected through the builder. `guzzlehttp/guzzle` is only suggested; install it, or another
  implementation, yourself.
- **`DanipaBuilder::timeoutMs()` removed.** Timeouts belong to the PSR-18 client you inject. The SDK cannot set one on
  an arbitrary client.
- **`maxRetries` now counts retries, not attempts.** The default is 2 (three attempts in total), and `0` disables
  retrying. `0` used to be rejected.
- **Retry policy:** only 408, 429, 502, 503, 504 and connection failures are retried. 500/501 are no longer retried.
  `Retry-After` is honoured (capped at 60 s). Backoff is full-jitter exponential (base 500 ms, cap 8 s).
- **Idempotency header:** `Idempotency-Key` (was `X-Idempotency-Key`). It is now sent on every POST/PUT/PATCH/DELETE: a
  UUIDv4 is generated when you give none, and it is reused across that call's retries. Keys over 255 characters are
  rejected client-side.
- **Payment-link and invoice models rebuilt from the server DTOs.** `CreatePaymentLinkRequest` takes a required `title`
  plus optional `description`, `amount`, `currency`, `isAmountFixed`, `minAmount`, `maxAmount`, `redirectUrl`,
  `expiresAt` and `customerId`. `CreateInvoiceRequest` takes a required `customerName` and `lineItems`
  (`InvoiceLineItemRequest`, with optional `taxRatePct`) plus optional `customerEmail`, `customerPhone`, `customerId`,
  `taxAmount`, `currency`, `dueDate` and `notes`. `PaymentLink` and `Invoice` expose the full `PaymentLinkResponse` /
  `InvoiceResponse`. The old fields that did not exist on the server (`Invoice::$amount`, `$merchantId`, ...) are gone.
- **`InvoiceStatus::CANCELLED` replaced by `VOIDED`**, which is the server's actual value, and `UNKNOWN` was added.
  Unknown server values parse as `UNKNOWN` (the raw string is on `$statusRaw`) instead of throwing a `ValueError`.
- **Errors:** `DanipaApiError` is now the base of typed subclasses (`AuthenticationError`, `PermissionError`,
  `NotFoundError`, `ConflictError`, `ValidationError`, `RateLimitError`, `ServerError`). Its `correlationId` is kept,
  and `requestId` is the one to quote to support. `DanipaNetworkError` is now abstract; catch it, or its
  `ConnectionError` / `TimeoutError` subclasses. A 2xx with an unreadable body now throws `ResponseParseError`, not
  `DanipaNetworkError`, because the call may have succeeded.
- **Resource methods return models with a `$meta` property** (`ResponseMeta`: status, requestId, idempotencyKey,
  idempotentReplay, attempts). The internal `HttpClient::request()` returns an `ApiResponse` instead of an array.
- **User-Agent** is now `danipa-php/<installed version> php/<runtime version>`. The version is read from Composer
  metadata; it used to be hard-coded as `danipa-php-sdk/0.1.0`.

#### Removed

- **`collections()`, `disbursements()` and `wallets()` resources**, with their models (`Collection`,
  `CreateCollectionRequest`, `Disbursement`, `CreateDisbursementRequest`, `WalletBalance`, `Payer`, `Recipient`,
  `Money`, and the status enums). They called `/v1/collections`, `/v1/disbursements` and `/v1/wallets/balance`. None of
  those routes has ever existed on the server, so every call failed. They will come back when there is a real API-key
  surface behind them (#5573).

#### Added

- `$danipa->merchants()->me()`: `GET /v1/merchants/me`, returning the full `MerchantResponse` as `Merchant`. This is
  the one route an API key can reach today (it needs the `account:read` scope).
- Money is parsed exactly: the JSON numbers the server writes (`125.00`) arrive as the decimal string `"125.00"`, never
  as a float. Money inputs accept `string|int|float` and are sent as strings.
- `DanipaWebhook::constructEvent($payload, $headers, $secrets)` verifies a delivery and returns a typed `WebhookEvent`
  (`type` from `X-Danipa-Event`, `deliveryId` from `X-Danipa-Delivery`, `timestamp`, and the decoded `data`). It throws
  `WebhookVerificationError`. Headers may be a PSR-7 message, a header map, or `$_SERVER`.
- `DanipaWebhook::verify()` accepts a PSR-7 stream as well as a string, a configurable tolerance (default 300 s), and a
  list of secrets for rotation.
- `PaymentLinkStatus` enum (`ACTIVE`, `INACTIVE`, `EXPIRED`, `UNKNOWN`).
- The cross-SDK webhook fixture is vendored at `test/fixtures/webhook-hmac.json`, so the public mirror's tests run on
  their own. CI fails if it drifts from `sdks/fixtures/`.
- PR CI (`.github/workflows/sdk-php-ci.yml`) runs PHP 8.2, 8.3, 8.4 and 8.5 with composer validate, PHPStan 2 at level
  max (over `src` and `test`) and PHPUnit 11.

#### Fixed

- Webhook verification no longer rejects a valid payload or timestamp that PHP's `empty()` treats as falsy (`"0"`).
  Empty checks are now `=== ''`.
- A malformed or mistyped response body is a typed `ResponseParseError`, never a PHP warning or `TypeError`.
- The Packagist dist archive no longer ships `test/`, `phpstan.neon`, `phpunit.xml.dist` or `.github` (`export-ignore`).
- The package name is consistently `danipa/sdk`, and the repository and support URLs point at `Danipa/php-sdk`.

## 0.1.0 — 2026-05-02

Live on Packagist: https://packagist.org/packages/danipa/sdk


Initial release. M1.5 of `DEVELOPER_PORTAL_FEATURE_GAPS`.

- `Danipa` client with auto-inferred sandbox/production base URL from API key prefix
- Resource classes: `collections`, `disbursements`, `wallets`, `paymentLinks`, `invoices`
- `Danipa\Sdk\DanipaWebhook::verify` / `computeSignature` for HMAC-SHA256 verification with 5-minute replay-window enforcement (cross-SDK fixture parity with Node + Java)
- 5xx + network retry with exponential backoff (3 attempts default, 200 ms → 400 ms → 800 ms capped at 2 s, +0–50 ms jitter)
- `X-Idempotency-Key` support on every mutating call (POST only)
- Typed error envelope: `DanipaApiError` (4xx/5xx) and `DanipaNetworkError` (transport)
- Final readonly DTOs with `final` and `readonly` properties (PHP 8.1+ compatible)
- PSR-18 `ClientInterface` for swappable HTTP transport — Guzzle 7 by default
- PSR-17 `RequestFactoryInterface` / `StreamFactoryInterface` for swappable factories
- phpstan level 8 + PSR-12 conformant
- Published to Packagist via the public mirror `Danipa/php-sdk` (subtree-split from monorepo on `sdk-php-v*` tags)
