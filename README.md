# danipa/sdk

Official PHP SDK for the Danipa payments API.

- Packagist: [`danipa/sdk`](https://packagist.org/packages/danipa/sdk)
- Source (public mirror): [github.com/Danipa/php-sdk](https://github.com/Danipa/php-sdk)
- Issues: [github.com/Danipa/php-sdk/issues](https://github.com/Danipa/php-sdk/issues)

```bash
composer require danipa/sdk guzzlehttp/guzzle
```

Requires **PHP 8.2+**. The SDK talks through any [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client and
[PSR-17](https://www.php-fig.org/psr/psr-17/) factories. It does not ship one: install Guzzle (as above), Symfony
HttpClient + `nyholm/psr7`, or any other implementation, and the SDK finds it through
[`php-http/discovery`](https://docs.php-http.org/en/latest/discovery.html). You can also inject one yourself, see
[HTTP transport and timeouts](#http-transport-and-timeouts).

> **Source of truth:** this package is developed in the `Danipa/danipa-digital-platform` monorepo (private) and published
> to Packagist through the public mirror `Danipa/php-sdk` on every `sdk-php-v*` tag. `git subtree split` keeps the mirror's
> tree in lockstep with `sdks/php/` in the monorepo.

## Quick start

```php
use Danipa\Sdk\Danipa;

// The API key is sent as the X-API-Key header. Its prefix picks the host:
//   dk_test_… → https://api.sandbox.danipa.com/ms
//   dk_live_… → https://api.danipa.com/ms   (production is not live yet: build() throws ConfigurationError)
$danipa = Danipa::builder()
    ->apiKey(getenv('DANIPA_API_KEY'))
    ->build();

$merchant = $danipa->merchants()->me();
echo $merchant->businessName, ' bills in ', $merchant->billingCurrency, PHP_EOL;
```

## Environments

The key decides where requests go, so a sandbox key can never reach production by accident:

| Key | Default base URL |
|---|---|
| `dk_test_…` | `https://api.sandbox.danipa.com/ms` (sandbox) |
| `dk_live_…` | `https://api.danipa.com/ms`. **Production is not live yet:** `build()` throws `ConfigurationError` ("production is not live yet — use a dk_test_ key against sandbox") unless you pass an explicit `baseUrl()`. Nothing is sent. |
| anything else | **`build()` throws `Danipa\Sdk\Errors\ConfigurationError`** (the SDK never guesses; the key is never echoed) |

An explicit choice always wins. Precedence: `baseUrl()` > `environment()` > the key prefix.

```php
use Danipa\Sdk\Environment;

Danipa::builder()->apiKey($key)->environment(Environment::SANDBOX)->build();
Danipa::builder()->apiKey($key)->baseUrl('http://localhost:8080/ms')->build(); // e.g. a local stack
```

## Resources

Every call below is on the API-key surface (the server's `ApiKeyAuthenticationFilter` route table) and needs the scope
shown. A key only sees and changes data of its own environment. Nothing else is callable with an API key, so the SDK
has no other methods.

| Call | Route | Scope |
|---|---|---|
| `$danipa->merchants()->me()` | `GET /v1/merchants/me` | `account:read` |
| `$danipa->paymentLinks()->create($req)` | `POST /v1/merchants/me/payment-links` | `payments:write` |
| `$danipa->paymentLinks()->list($page, $size)` | `GET /v1/merchants/me/payment-links` | `payments:read` |
| `$danipa->paymentLinks()->update($id, $req)` | `PUT /v1/merchants/me/payment-links/{id}` | `payments:write` |
| `$danipa->paymentLinks()->delete($id)` | `DELETE /v1/merchants/me/payment-links/{id}` | `payments:write` |
| `$danipa->invoices()->create($req)` | `POST /v1/merchants/me/invoices` | `payments:write` |
| `$danipa->invoices()->list($status, $q, $page, $size)` | `GET /v1/merchants/me/invoices` | `payments:read` |
| `$danipa->invoices()->get($id)` | `GET /v1/merchants/me/invoices/{id}` | `payments:read` |
| `$danipa->invoices()->send($id)` | `POST /v1/merchants/me/invoices/{id}/send` | `payments:write` |
| `$danipa->invoices()->void($id)` | `POST /v1/merchants/me/invoices/{id}/void` | `payments:write` |
| `$danipa->balances()->list()` | `GET /v1/merchants/me/wallet/balances` | `payments:read` |
| `Danipa\Sdk\DanipaWebhook` | (static) `verify`, `constructEvent`, `computeSignature` | n/a |

A key without the scope gets `PermissionError` with `errorCode === 'API_KEY_SCOPE_DENIED'`. There is no "get one payment
link" route for a key; use `list()`. Ids are UUIDs and are checked before anything is sent.

### Payment links

```php
use Danipa\Sdk\Model\CreatePaymentLinkRequest;
use Danipa\Sdk\Model\UpdatePaymentLinkRequest;

$link = $danipa->paymentLinks()->create(new CreatePaymentLinkRequest(
    title: 'Flat white',
    amount: '12.50',            // money as a decimal string; omit currency to use the merchant's billing currency
    redirectUrl: 'https://shop.example/thanks',
));
echo $link->url, ' ', $link->amount, ' ', $link->status->value;

// Partial update of an ACTIVE link: only the fields you set are sent.
$link = $danipa->paymentLinks()->update($link->id, new UpdatePaymentLinkRequest(amount: '14.00'));

$page = $danipa->paymentLinks()->list(page: 0, size: 50);   // zero-based pages, size 1–200
foreach ($page->items as $l) { /* ... */ }
$page->totalElements; $page->hasNext();

$result = $danipa->paymentLinks()->delete($link->id);
$result->deleted;      // true: removed (it never took a payment)
$result->deactivated;  // true: it had payments, so it was deactivated instead
```

Open-amount links: `isAmountFixed: false, minAmount: '5', maxAmount: '500'`.

### Invoices

```php
use Danipa\Sdk\Model\CreateInvoiceRequest;
use Danipa\Sdk\Model\InvoiceLineItemRequest;
use Danipa\Sdk\Model\InvoiceStatus;

$invoice = $danipa->invoices()->create(new CreateInvoiceRequest(   // created as DRAFT
    customerName: 'Ama K.',
    lineItems: [new InvoiceLineItemRequest('Consulting', 2, '250.00', taxRatePct: '13')],
    customerEmail: 'ama@example.com',
    dueDate: '2026-11-01',
));

$invoice = $danipa->invoices()->send($invoice->id);   // DRAFT → SENT; $invoice->paymentLinkUrl is set
$invoice = $danipa->invoices()->get($invoice->id);
$page    = $danipa->invoices()->list(status: InvoiceStatus::SENT, q: 'Ama');
$invoice = $danipa->invoices()->void($invoice->id);   // unpaid only; a paid invoice is a ConflictError (409)

if ($invoice->status === InvoiceStatus::UNKNOWN) {
    // The server sent a status this SDK version doesn't know yet; it is kept in $invoice->statusRaw.
}
```

`InvoiceStatus` is `DRAFT`, `SENT`, `PAID`, `OVERDUE`, `VOIDED`, or `UNKNOWN` for any value added on the server later.
`PaymentLinkStatus` works the same way. An unknown value never throws. Updating a draft, the PDF, stats, recording an
offline payment and bulk import are dashboard-only; they are not on the API-key surface.

### Balances

```php
foreach ($danipa->balances()->list() as $b) {
    echo $b->currency, ' available ', $b->availableBalance, ' pending ', $b->pendingBalance, PHP_EOL;
}
```

One entry per currency the merchant holds (an empty list until something has settled). Business money only, never the
owner's personal wallet.

### Money

The server writes money as a JSON **number** (`125.00`). The SDK reads it as the exact decimal **string** the server sent
(`"125.00"`), never as a float, so trailing zeros and precision survive. Pass money in as a string (preferred); ints and
floats are accepted and converted to strings.

## Idempotency

Every `POST` / `PUT` / `PATCH` / `DELETE` carries an `Idempotency-Key` header. If you don't pass one, the SDK generates a
UUIDv4 and reuses **the same key for every retry of that call**, so a retry can never charge twice. To make a call
idempotent across processes or restarts, pass your own key (255 characters at most; longer keys are rejected before
anything is sent):

```php
use Danipa\Sdk\RequestOptions;

$link = $danipa->paymentLinks()->create($req, RequestOptions::withIdempotencyKey('order-' . $order->id));

$link->meta->idempotencyKey;    // the key that was sent (yours, or the generated one)
$link->meta->idempotentReplay;  // true when the server replayed a cached result (X-Idempotent-Replay: true)
$link->meta->requestId;         // X-Correlation-Id / X-Request-Id, for support tickets
```

The same key and replay flag are on every error, as `$e->idempotencyKey` and `$e->idempotentReplay`.

## Retries

- Retried: connection failures and HTTP **408, 429, 502, 503, 504**.
- Never retried: **500, 501**, any other 4xx, and any 2xx.
- Reads are always safe to retry. Writes are safe because they always carry an idempotency key.
- A `Retry-After` header (seconds) is honoured, capped at 60 s. Otherwise the SDK uses exponential backoff with full
  jitter: a random delay in `[0, min(8 s, 500 ms × 2^n)]`.
- `maxRetries` is the number of **retries** after the first attempt. The default is 2, and `0` disables retrying.

```php
$danipa = Danipa::builder()->apiKey($key)->maxRetries(4)->build();
```

## Error handling

Every exception extends `Danipa\Sdk\Errors\DanipaError` (a `RuntimeException`).

| Class | When |
|---|---|
| `AuthenticationError` | 401, e.g. `INVALID_API_KEY` |
| `PermissionError` | 403, e.g. `API_KEY_SCOPE_DENIED`, `IP_NOT_ALLOWED`, `AMBIGUOUS_CREDENTIALS` |
| `NotFoundError` | 404 |
| `ConflictError` | 409, e.g. `IDEMPOTENCY_KEY_COLLISION` |
| `ValidationError` | 400 / 422; field problems are in `$e->fieldErrors` |
| `RateLimitError` | 429 after retries; `$e->retryAfter()` gives seconds |
| `ServerError` | 5xx |
| `DanipaApiError` | the base of all the above, and what any other status (e.g. 402 `BILLING_SUSPENDED`) raises |
| `ConnectionError` / `TimeoutError` | no response (both extend `DanipaNetworkError`) |
| `ResponseParseError` | a **2xx** whose body could not be parsed. The call probably **succeeded**, so do not retry it blindly; reuse `$e->idempotencyKey` if you must |

Every `DanipaApiError` carries `status`, `errorCode`, `getMessage()`, `requestId`, `raw` (the body), and, when the server
sent them, `fieldErrors`, `retryable`, `retryAfterSeconds`, `traceId`, `correlationId` and `messageKey`. The SDK parses
both server error shapes: the `ApiStatus` envelope and the flat `{code, description}` that the security and rate-limit
filters return.

```php
use Danipa\Sdk\Errors\DanipaApiError;
use Danipa\Sdk\Errors\DanipaNetworkError;
use Danipa\Sdk\Errors\PermissionError;

try {
    $danipa->merchants()->me();
} catch (PermissionError $e) {
    error_log("forbidden: {$e->errorCode} (request {$e->requestId})");
} catch (DanipaApiError $e) {
    error_log(sprintf('[%d] %s: %s', $e->status, $e->errorCode, $e->getMessage()));
} catch (DanipaNetworkError $e) {
    error_log('network: ' . $e->getMessage());
}
```

## Verifying webhooks

Each delivery carries `X-Danipa-Signature: sha256=<hex>`, `X-Danipa-Timestamp`, `X-Danipa-Event` and
`X-Danipa-Delivery`. `constructEvent` verifies the signature and the timestamp (default tolerance 300 s), then returns a
typed `WebhookEvent`. If anything is wrong it throws `WebhookVerificationError`. Always pass the **raw request body**.
Re-encoding decoded JSON changes the bytes and breaks the signature.

```php
use Danipa\Sdk\DanipaWebhook;
use Danipa\Sdk\Errors\WebhookVerificationError;

try {
    $event = DanipaWebhook::constructEvent(
        file_get_contents('php://input'),   // raw bytes (a PSR-7 StreamInterface works too)
        getallheaders(),                    // or a PSR-7 request, Symfony/Laravel $request->headers->all(), or $_SERVER
        [getenv('DANIPA_WEBHOOK_SECRET'), getenv('DANIPA_WEBHOOK_SECRET_PREVIOUS')], // several secrets while rotating
    );
} catch (WebhookVerificationError $e) {
    http_response_code(400);
    exit;
}

// $event->type ('payment.completed', ...), $event->deliveryId (dedupe on it), $event->data (decoded body)
http_response_code(200);
```

Laravel and Symfony: `DanipaWebhook::constructEvent($request->getContent(), $request->headers->all(), $secrets)`.

For a boolean check, use `DanipaWebhook::verify($payload, $signature, $timestamp, $secretOrSecrets, $toleranceSeconds)`.
The signing algorithm is fixed by a cross-SDK fixture (`test/fixtures/webhook-hmac.json`), which the Node, Python and Java
SDKs also pass.

## HTTP transport and timeouts

```php
$danipa = Danipa::builder()
    ->apiKey(getenv('DANIPA_API_KEY'))
    ->transport(new \GuzzleHttp\Client(['timeout' => 30, 'connect_timeout' => 10]))
    ->build();
```

- `transport()` takes any PSR-18 client. `requestFactory()` and `streamFactory()` take PSR-17 factories. Whatever you
  inject is used as-is, and whatever you leave out is discovered.
- **Timeouts belong to the client.** The SDK cannot set a timeout on an arbitrary PSR-18 client, so configure it where
  you build the client, as above. A timed-out call surfaces as `TimeoutError`.
- The SDK sends `User-Agent: danipa-php/<installed version> php/<PHP version>`. The version is read from Composer's
  installed-package metadata, not hard-coded.

## Sandbox smoke test

`examples/smoke_sandbox.php` walks the whole API-key surface once against the sandbox (it creates, then deletes, a
payment link, and creates, sends and voids an invoice with no customer contact). It prints only step names and
OK / FAIL, never the key or response bodies. It refuses anything but a `dk_test_` key. It is not run in CI.

```bash
composer install
DANIPA_API_KEY=dk_test_... php examples/smoke_sandbox.php
```

## License

MIT.
