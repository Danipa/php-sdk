# danipa/sdk

Official PHP SDK for the Danipa payments API.

```bash
composer require danipa/sdk
```

Requires PHP 8.1+. Runtime dependencies: [Guzzle 7](https://github.com/guzzle/guzzle) (default transport) and `psr/http-message` / `psr/http-client` / `psr/http-factory`. Bring your own PSR-18 client to swap Guzzle out — see [Pluggable HTTP transport](#pluggable-http-transport).

> **Source of truth:** this package is developed in the `paboagye/danipa-digital-platform` monorepo (private) and published to Packagist via the public mirror `danipa/php-sdk` on every `sdk-php-v*` tag — `git subtree split` keeps the mirror's tree in lockstep with `sdks/php/` in the monorepo.

## Quick start

```php
use Danipa\Sdk\Danipa;
use Danipa\Sdk\RequestOptions;
use Danipa\Sdk\Model\CreateCollectionRequest;
use Danipa\Sdk\Model\Payer;

// API key prefix decides sandbox vs production:
//   dk_test_… → https://api.sandbox.danipa.com/ms
//   dk_live_… → https://api.danipa.com/ms
$danipa = Danipa::builder()
    ->apiKey(getenv('DANIPA_API_KEY'))
    ->build();

$collection = $danipa->collections()->create(
    new CreateCollectionRequest(
        amount: '125.00',
        currency: 'GHS',
        payer: Payer::phone('+233244112233', 'Ama K.'),
    ),
    RequestOptions::withIdempotencyKey('order-1234'),
);

echo $collection->status->value; // "PENDING"
```

## Resources

| Resource | Methods |
|---|---|
| `$danipa->collections()` | `create`, `get` |
| `$danipa->disbursements()` | `create`, `get` |
| `$danipa->wallets()` | `getBalance`, `getBalance($currency)` |
| `$danipa->paymentLinks()` | `create` |
| `$danipa->invoices()` | `create` |
| `Danipa\Sdk\DanipaWebhook` | `verify`, `computeSignature` (static) |

## Idempotency

Pass an idempotency key on every mutating call via `RequestOptions::withIdempotencyKey(...)`. The backend dedupes on `(merchantId, key)` for 24 hours, so retrying a failed request with the same key is safe.

```php
use Danipa\Sdk\Model\CreateDisbursementRequest;
use Danipa\Sdk\Model\Recipient;

$danipa->disbursements()->create(
    new CreateDisbursementRequest(
        amount: '500.00',
        currency: 'GHS',
        recipient: Recipient::phone('+233244998877', 'Kojo'),
    ),
    RequestOptions::withIdempotencyKey('payout-' . $order->id),
);
```

The header is sent on `POST` requests only — `GET` calls ignore the option, matching Node + Java.

## Retry & timeout

- Auto-retries 5xx and network errors. Default 3 attempts with exponential backoff (200 ms → 400 ms → 800 ms, capped at 2 s, +0–50 ms jitter).
- 4xx never retries — fix the request and re-call.
- 30 s per-request timeout. Override via the builder.

```php
$danipa = Danipa::builder()
    ->apiKey(getenv('DANIPA_API_KEY'))
    ->maxRetries(5)
    ->timeoutMs(60000)
    ->build();
```

## Verifying webhooks

The verifier is namespaced as `Danipa\Sdk\DanipaWebhook` and works in plain PHP, Symfony, Laravel, or any framework that exposes the raw request body and headers.

### Plain PHP

```php
use Danipa\Sdk\DanipaWebhook;

$payload   = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_DANIPA_SIGNATURE'] ?? '';
$timestamp = $_SERVER['HTTP_X_DANIPA_TIMESTAMP'] ?? '';
$secret    = getenv('DANIPA_WEBHOOK_SECRET');

if (!DanipaWebhook::verify($payload, $signature, $timestamp, $secret)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$event = json_decode($payload, true);
// … dispatch event …
http_response_code(200);
```

### Symfony

```php
use Danipa\Sdk\DanipaWebhook;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/webhooks/danipa', methods: ['POST'])]
public function __invoke(Request $request): Response
{
    $ok = DanipaWebhook::verify(
        $request->getContent(),
        $request->headers->get('X-Danipa-Signature', ''),
        $request->headers->get('X-Danipa-Timestamp', ''),
        $_ENV['DANIPA_WEBHOOK_SECRET'],
    );
    if (!$ok) {
        return new Response('', 401);
    }
    // … decode + dispatch …
    return new Response('', 200);
}
```

### Laravel

```php
use Danipa\Sdk\DanipaWebhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/danipa', function (Request $request) {
    $ok = DanipaWebhook::verify(
        $request->getContent(),                            // raw body — NOT $request->all()
        $request->header('X-Danipa-Signature', ''),
        $request->header('X-Danipa-Timestamp', ''),
        env('DANIPA_WEBHOOK_SECRET'),
    );
    abort_if(!$ok, 401);
    // … dispatch …
    return response()->noContent();
});
```

The 5-minute replay window is enforced server-side. Always pass the **raw request body** — re-serializing through `json_encode(json_decode(...))` rewrites the bytes and breaks the signature.

## Error handling

```php
use Danipa\Sdk\Errors\DanipaApiError;
use Danipa\Sdk\Errors\DanipaNetworkError;

try {
    $danipa->collections()->create($req);
} catch (DanipaApiError $e) {
    // Backend rejected the request — 4xx or exhausted-retry 5xx.
    fwrite(STDERR, sprintf(
        "[%d] %s: %s (correlationId: %s)\n",
        $e->status,
        $e->errorCode,
        $e->getMessage(),
        $e->correlationId ?? '-',
    ));
} catch (DanipaNetworkError $e) {
    // Never reached the backend (DNS, TLS, timeout, connection reset).
    fwrite(STDERR, 'Network failure: ' . ($e->getPrevious()?->getMessage() ?? $e->getMessage()) . "\n");
}
```

Both classes extend `Danipa\Sdk\Errors\DanipaError`, which itself extends `RuntimeException` — catch the base to handle anything from the SDK.

## Pluggable HTTP transport

The default transport is Guzzle 7. Pass any [PSR-18](https://www.php-fig.org/psr/psr-18/) `ClientInterface` to swap it — useful when your app already runs Symfony HttpClient, Buzz, or a managed Guzzle pool with custom middleware:

```php
use Symfony\Component\HttpClient\Psr18Client;

$danipa = Danipa::builder()
    ->apiKey(getenv('DANIPA_API_KEY'))
    ->transport(new Psr18Client())
    ->build();
```

Optional PSR-17 factories (`requestFactory`, `streamFactory`) can be injected the same way; both default to Guzzle's `HttpFactory`.

## Event reference

Full webhook event catalog with payload schemas: [developer.sandbox.danipa.com/webhooks/events](https://developer.sandbox.danipa.com/webhooks/events).

## License

MIT.
