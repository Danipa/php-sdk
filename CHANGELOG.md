# Changelog

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
- Published to Packagist via the public mirror `danipa/php-sdk` (subtree-split from monorepo on `sdk-php-v*` tags)
