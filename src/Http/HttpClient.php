<?php

declare(strict_types=1);

namespace Danipa\Sdk\Http;

use Danipa\Sdk\Errors\AuthenticationError;
use Danipa\Sdk\Errors\ConfigurationError;
use Danipa\Sdk\Errors\ConflictError;
use Danipa\Sdk\Errors\ConnectionError;
use Danipa\Sdk\Errors\DanipaApiError;
use Danipa\Sdk\Errors\DanipaNetworkError;
use Danipa\Sdk\Errors\FieldError;
use Danipa\Sdk\Errors\NotFoundError;
use Danipa\Sdk\Errors\PermissionError;
use Danipa\Sdk\Errors\RateLimitError;
use Danipa\Sdk\Errors\ServerError;
use Danipa\Sdk\Errors\TimeoutError;
use Danipa\Sdk\Errors\ValidationError;
use Danipa\Sdk\Internal\Json;
use Danipa\Sdk\Internal\MalformedResponse;
use Danipa\Sdk\Internal\Version;
use Danipa\Sdk\Environment;
use Danipa\Sdk\RequestOptions;
use Danipa\Sdk\ResponseMeta;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Internal HTTP wiring shared by every resource. Owns authentication
 * (`X-API-Key`), the `Idempotency-Key` contract, safe retries and error
 * mapping. Consumers go through {@see \Danipa\Sdk\Danipa}.
 *
 * Transport: any PSR-18 client + PSR-17 factories. Injected instances are
 * used as-is; otherwise they are discovered with `php-http/discovery`. The
 * SDK sets no timeout of its own — configure timeouts on the client you inject.
 *
 * @internal
 */
final class HttpClient
{
    public const DEFAULT_MAX_RETRIES = 2;

    public const BACKOFF_BASE_MS = 500;
    public const BACKOFF_CAP_MS = 8000;
    public const RETRY_AFTER_CAP_SECONDS = 60;

    /**
     * The {@see ConfigurationError} raised when a client would default to the
     * live host. Production is not live yet, so failing at construction beats
     * an opaque DNS error on the first call; an explicit base URL is never
     * blocked. A later 0.x release removes this guard.
     */
    public const PRODUCTION_NOT_LIVE_MESSAGE = 'Danipa SDK: production is not live yet — use a dk_test_ key against '
        . 'sandbox. (To reach a live host anyway, pass an explicit baseUrl().)';

    private const RETRYABLE_STATUSES = [408, 429, 502, 503, 504];
    private const MUTATING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    private readonly string $baseUrl;
    private readonly ClientInterface $client;
    private readonly RequestFactoryInterface $requestFactory;
    private readonly StreamFactoryInterface $streamFactory;
    /** @var \Closure(int): void */
    private readonly \Closure $sleeper;
    /** @var \Closure(int): int */
    private readonly \Closure $random;
    /** @var \Closure(): string */
    private readonly \Closure $keyGenerator;

    /**
     * @param int                        $maxRetries   Number of RETRIES after the first attempt (0 = none).
     * @param (\Closure(int): void)|null $sleeper      Receives the delay in milliseconds (tests inject a fake clock).
     * @param (\Closure(int): int)|null  $random       Returns a uniform int in [0, $max] (backoff jitter).
     * @param (\Closure(): string)|null  $keyGenerator Generates an idempotency key (default UUIDv4).
     */
    public function __construct(
        private readonly string $apiKey,
        ?string $baseUrl = null,
        private readonly int $maxRetries = self::DEFAULT_MAX_RETRIES,
        ?ClientInterface $client = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?\Closure $sleeper = null,
        ?\Closure $random = null,
        ?\Closure $keyGenerator = null,
    ) {
        if (trim($apiKey) === '') {
            throw new \InvalidArgumentException('Danipa SDK: apiKey is required');
        }
        if (preg_match('/[\x00-\x20\x7F]/', $apiKey) === 1) {
            throw new \InvalidArgumentException('Danipa SDK: apiKey must not contain whitespace or control characters');
        }
        if ($maxRetries < 0) {
            throw new \InvalidArgumentException('Danipa SDK: maxRetries must be >= 0');
        }
        if ($baseUrl !== null && trim($baseUrl) === '') {
            throw new ConfigurationError('Danipa SDK: baseUrl must not be blank');
        }
        if ($baseUrl === null && Environment::fromApiKey($apiKey) === Environment::LIVE) {
            throw new ConfigurationError(self::PRODUCTION_NOT_LIVE_MESSAGE);
        }
        $this->baseUrl = rtrim($baseUrl ?? self::inferBaseUrl($apiKey), '/');
        $this->client = $client ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
        $this->sleeper = $sleeper ?? static function (int $ms): void {
            usleep($ms * 1000);
        };
        $this->random = $random ?? static fn (int $max): int => $max <= 0 ? 0 : random_int(0, $max);
        $this->keyGenerator = $keyGenerator ?? self::uuid4(...);
    }

    /**
     * `dk_test_…` → sandbox, `dk_live_…` → production (not yet live). Any
     * other prefix is a {@see ConfigurationError}: the SDK never guesses.
     *
     * @throws ConfigurationError
     */
    public static function inferBaseUrl(string $apiKey): string
    {
        return Environment::fromApiKey($apiKey)->baseUrl();
    }

    /**
     * @param array<string, mixed>|null  $body
     * @param array<string, scalar|null> $query
     * @throws \Danipa\Sdk\Errors\DanipaError
     */
    public function request(
        string $method,
        string $path,
        ?array $body = null,
        array $query = [],
        ?RequestOptions $options = null,
    ): ApiResponse {
        $method = strtoupper($method);
        $options ??= RequestOptions::none();
        $mutating = \in_array($method, self::MUTATING_METHODS, true);
        if (!$mutating && !\in_array($method, self::SAFE_METHODS, true)) {
            throw new \InvalidArgumentException(sprintf('Danipa SDK: unsupported HTTP method %s', $method));
        }

        // One key per logical call, reused by every retry of it.
        $idempotencyKey = null;
        if ($mutating) {
            $idempotencyKey = $options->idempotencyKey ?? ($this->keyGenerator)();
            RequestOptions::assertValidKey($idempotencyKey);
        }

        $request = $this->buildRequest($method, $path, $body, $query, $idempotencyKey);

        // Every method reaching this point is retry-safe: reads are
        // idempotent by definition and every write carries a key.
        for ($attempt = 0;; $attempt++) {
            try {
                $response = $this->client->sendRequest($request);
            } catch (ClientExceptionInterface $e) {
                // A RequestExceptionInterface means the request itself is
                // unsendable — retrying cannot help.
                $retryable = !$e instanceof RequestExceptionInterface;
                if ($retryable && $attempt < $this->maxRetries) {
                    ($this->sleeper)($this->backoffMs($attempt));
                    continue;
                }
                throw $this->toNetworkError($method, $path, $attempt + 1, $e, $idempotencyKey);
            }

            $status = $response->getStatusCode();
            if ($status >= 200 && $status < 300) {
                return new ApiResponse(
                    (string) $response->getBody(),
                    $this->meta($response, $idempotencyKey, $attempt + 1, null),
                );
            }
            if (\in_array($status, self::RETRYABLE_STATUSES, true) && $attempt < $this->maxRetries) {
                $retryAfter = self::retryAfterSeconds($response);
                ($this->sleeper)(
                    $retryAfter !== null
                        ? min($retryAfter, self::RETRY_AFTER_CAP_SECONDS) * 1000
                        : $this->backoffMs($attempt),
                );
                continue;
            }
            throw $this->toApiError($response, $idempotencyKey, $attempt + 1);
        }
    }

    /**
     * @param array<string, mixed>|null  $body
     * @param array<string, scalar|null> $query
     */
    private function buildRequest(
        string $method,
        string $path,
        ?array $body,
        array $query,
        ?string $idempotencyKey,
    ): RequestInterface {
        $request = $this->requestFactory->createRequest($method, $this->buildUrl($path, $query))
            ->withHeader('X-API-Key', $this->apiKey)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', Version::userAgent());
        if ($idempotencyKey !== null) {
            $request = $request->withHeader('Idempotency-Key', $idempotencyKey);
        }
        if ($body !== null) {
            // A request body is always a JSON object; an empty PHP array would encode as `[]`.
            $json = json_encode(
                $body === [] ? new \stdClass() : $body,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
            );
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($json));
        }
        // Never send Authorization: X-API-Key + Authorization is rejected by
        // the server with 403 AMBIGUOUS_CREDENTIALS.
        return $request->withoutHeader('Authorization');
    }

    /** @param array<string, scalar|null> $query */
    private function buildUrl(string $path, array $query): string
    {
        $url = $this->baseUrl . (str_starts_with($path, '/') ? $path : '/' . $path);
        $filtered = [];
        foreach ($query as $key => $value) {
            if ($value === null) {
                continue;
            }
            $filtered[$key] = \is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }
        if ($filtered !== []) {
            $url .= '?' . http_build_query($filtered, '', '&', PHP_QUERY_RFC3986);
        }
        return $url;
    }

    /** Full-jitter exponential backoff: uniform in [0, min(cap, base × 2^attempt)]. */
    private function backoffMs(int $attempt): int
    {
        $ceiling = (int) min(self::BACKOFF_CAP_MS, self::BACKOFF_BASE_MS * (2 ** min($attempt, 16)));
        return max(0, min($ceiling, ($this->random)($ceiling)));
    }

    /** `Retry-After` in whole seconds, or null when absent / not delta-seconds. */
    private static function retryAfterSeconds(ResponseInterface $response): ?int
    {
        $value = trim($response->getHeaderLine('Retry-After'));
        if ($value === '' || !ctype_digit($value) || \strlen($value) > 9) {
            return null;
        }
        return (int) $value;
    }

    /** @param array<array-key, mixed>|null $body */
    private function meta(
        ResponseInterface $response,
        ?string $idempotencyKey,
        int $attempts,
        ?array $body,
    ): ResponseMeta {
        return new ResponseMeta(
            status: $response->getStatusCode(),
            requestId: self::requestId($response, $body),
            idempotencyKey: $idempotencyKey,
            idempotentReplay: strtolower(trim($response->getHeaderLine('X-Idempotent-Replay'))) === 'true',
            attempts: $attempts,
        );
    }

    /** @param array<array-key, mixed>|null $body */
    private static function requestId(ResponseInterface $response, ?array $body): ?string
    {
        foreach (['X-Correlation-Id', 'X-Request-Id'] as $header) {
            $value = trim($response->getHeaderLine($header));
            if ($value !== '') {
                return $value;
            }
        }
        if ($body !== null) {
            foreach (['correlationId', 'traceId'] as $field) {
                $value = self::strOrNull($body, $field);
                if ($value !== null && $value !== '') {
                    return $value;
                }
            }
        }
        return null;
    }

    private function toNetworkError(
        string $method,
        string $path,
        int $attempts,
        ClientExceptionInterface $e,
        ?string $idempotencyKey,
    ): DanipaNetworkError {
        $message = sprintf(
            'Danipa SDK: %s %s failed after %d attempt%s: %s',
            $method,
            $path,
            $attempts,
            $attempts === 1 ? '' : 's',
            $e->getMessage(),
        );
        $isTimeout = preg_match('/timed? ?out|timeout|cURL error 28/i', $e->getMessage()) === 1;
        return $isTimeout
            ? new TimeoutError($message, $e, $idempotencyKey)
            : new ConnectionError($message, $e, $idempotencyKey);
    }

    /**
     * Map a non-2xx response to a typed error. Understands both the `ApiStatus`
     * envelope and the flat `{code, description}` filter shape.
     */
    private function toApiError(ResponseInterface $response, ?string $idempotencyKey, int $attempts): DanipaApiError
    {
        $status = $response->getStatusCode();
        $rawText = (string) $response->getBody();
        $body = null;
        if ($rawText !== '') {
            try {
                $decoded = Json::decode($rawText);
                if (\is_array($decoded)) {
                    $body = $decoded;
                }
            } catch (MalformedResponse) {
                // Non-JSON error body (e.g. a proxy's HTML page).
            }
        }

        $code = 'UNKNOWN';
        $message = sprintf('HTTP %d', $status);
        $fieldErrors = [];
        $retryable = null;
        $retryAfter = self::retryAfterSeconds($response);
        $traceId = null;
        $correlationId = null;
        $messageKey = null;

        if ($body !== null) {
            // ApiStatus envelope → errors[0]; flat filter shape → the root.
            $errors = $body['errors'] ?? null;
            $first = \is_array($errors) && isset($errors[0]) && \is_array($errors[0]) ? $errors[0] : $body;
            $firstCode = self::strOrNull($first, 'code');
            if ($firstCode !== null && $firstCode !== '') {
                $code = $firstCode;
            }
            foreach (['description', 'message'] as $field) {
                $text = self::strOrNull($first, $field);
                if ($text !== null && $text !== '') {
                    $message = $text;
                    break;
                }
            }
            $rawFieldErrors = $body['fieldErrors'] ?? null;
            if (\is_array($rawFieldErrors)) {
                foreach ($rawFieldErrors as $fe) {
                    if (\is_array($fe)) {
                        $fieldErrors[] = new FieldError(
                            self::strOrNull($fe, 'field'),
                            self::strOrNull($fe, 'code'),
                            self::strOrNull($fe, 'message'),
                        );
                    }
                }
            }
            $rawRetryable = $body['retryable'] ?? null;
            if (\is_bool($rawRetryable)) {
                $retryable = $rawRetryable;
            }
            $rawRetryAfter = $body['retryAfterSeconds'] ?? null;
            if ($retryAfter === null && \is_int($rawRetryAfter)) {
                $retryAfter = $rawRetryAfter;
            }
            $traceId = self::strOrNull($body, 'traceId');
            $correlationId = self::strOrNull($body, 'correlationId');
            $messageKey = self::strOrNull($body, 'messageKey');
        }

        $meta = $this->meta($response, $idempotencyKey, $attempts, $body);
        $raw = $body ?? ($rawText !== '' ? $rawText : null);

        $class = match (true) {
            $status === 401 => AuthenticationError::class,
            $status === 403 => PermissionError::class,
            $status === 404 => NotFoundError::class,
            $status === 409 => ConflictError::class,
            $status === 400, $status === 422 => ValidationError::class,
            $status === 429 => RateLimitError::class,
            $status >= 500 => ServerError::class,
            default => DanipaApiError::class,
        };

        return new $class(
            status: $status,
            errorCode: $code,
            message: $message,
            requestId: $meta->requestId,
            raw: $raw,
            fieldErrors: $fieldErrors,
            retryable: $retryable,
            retryAfterSeconds: $retryAfter,
            traceId: $traceId,
            correlationId: $correlationId,
            messageKey: $messageKey,
            idempotencyKey: $idempotencyKey,
            idempotentReplay: $meta->idempotentReplay,
        );
    }

    /** @param array<array-key, mixed> $data */
    private static function strOrNull(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        return \is_string($value) ? $value : null;
    }

    /** RFC 4122 version-4 UUID from a CSPRNG. */
    public static function uuid4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = \chr((\ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = \chr((\ord($bytes[8]) & 0x3F) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
