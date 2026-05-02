<?php

declare(strict_types=1);

namespace Danipa\Sdk\Http;

use Danipa\Sdk\Errors\DanipaApiError;
use Danipa\Sdk\Errors\DanipaNetworkError;
use Danipa\Sdk\RequestOptions;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory as GuzzlePsr17Factory;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Internal HTTP wiring. Consumers never touch this — they go through {@see
 * \Danipa\Sdk\Danipa} and its resource classes. Centralizes auth header
 * injection, idempotency-key forwarding, retry-on-5xx with exponential
 * backoff, and JSON ser/de.
 *
 * Mirrors Node SDK's `http.ts` 1:1 — same default timeout (30s), same retry
 * budget (3 attempts), same backoff curve (200 ms × 2^(attempt-1) capped at
 * 2s, +0–50 ms jitter), same header names (`X-Idempotency-Key`).
 *
 * @internal
 */
final class HttpClient
{
    public const SANDBOX_BASE_URL = 'https://api.sandbox.danipa.com/ms';
    public const PRODUCTION_BASE_URL = 'https://api.danipa.com/ms';
    public const USER_AGENT = 'danipa-php-sdk/0.1.0';
    public const DEFAULT_TIMEOUT_MS = 30000;
    public const DEFAULT_MAX_RETRIES = 3;

    private const BACKOFF_BASE_MS = 200;
    private const BACKOFF_CAP_MS = 2000;
    private const BACKOFF_JITTER_MS = 50;

    private readonly string $baseUrl;
    private readonly ClientInterface $client;
    private readonly RequestFactoryInterface $requestFactory;
    private readonly StreamFactoryInterface $streamFactory;

    public function __construct(
        private readonly string $apiKey,
        ?string $baseUrl = null,
        int $timeoutMs = self::DEFAULT_TIMEOUT_MS,
        private readonly int $maxRetries = self::DEFAULT_MAX_RETRIES,
        ?ClientInterface $client = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        private readonly ?\Closure $sleeper = null,
    ) {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('Danipa SDK: apiKey is required');
        }
        if ($maxRetries < 1) {
            throw new \InvalidArgumentException('Danipa SDK: maxRetries must be >= 1');
        }
        $resolvedBase = $baseUrl ?? self::inferBaseUrl($apiKey);
        $this->baseUrl = rtrim($resolvedBase, '/');
        $this->client = $client ?? new GuzzleClient([
            'http_errors' => false,
            'timeout' => $timeoutMs / 1000.0,
            'connect_timeout' => 10.0,
        ]);
        $factory = new GuzzlePsr17Factory();
        $this->requestFactory = $requestFactory ?? $factory;
        $this->streamFactory = $streamFactory ?? $factory;
    }

    /**
     * Sandbox vs production is inferred from the API key prefix:
     * `dk_live_…` → production, anything else → sandbox.
     */
    public static function inferBaseUrl(string $apiKey): string
    {
        return str_starts_with($apiKey, 'dk_live_')
            ? self::PRODUCTION_BASE_URL
            : self::SANDBOX_BASE_URL;
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    public function request(
        string $method,
        string $path,
        ?array $body = null,
        array $query = [],
        ?RequestOptions $options = null,
    ): array {
        $options ??= RequestOptions::none();
        $url = $this->buildUrl($path, $query);
        $request = $this->requestFactory->createRequest($method, $url)
            ->withHeader('Authorization', 'Bearer ' . $this->apiKey)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', self::USER_AGENT);
        if ($body !== null) {
            $json = json_encode($body, JSON_THROW_ON_ERROR);
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($json));
        }
        // Idempotency-Key on POSTs only — match Node's behavior.
        $idempotencyKey = $options->idempotencyKey;
        if ($idempotencyKey !== null && strtoupper($method) === 'POST') {
            $request = $request->withHeader('X-Idempotency-Key', $idempotencyKey);
        }

        $lastError = null;
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $response = $this->client->sendRequest($request);
                $status = $response->getStatusCode();
                if ($status >= 200 && $status < 300) {
                    return $this->parseBody($response);
                }
                if ($status < 500) {
                    throw $this->toApiError($response);
                }
                if ($attempt === $this->maxRetries) {
                    throw $this->toApiError($response);
                }
                $this->backoff($attempt);
            } catch (DanipaApiError $e) {
                throw $e;
            } catch (ClientExceptionInterface $e) {
                $lastError = $e;
                if ($attempt === $this->maxRetries) {
                    throw new DanipaNetworkError(
                        sprintf(
                            'Danipa SDK: %s %s failed after %d attempts',
                            $method,
                            $path,
                            $this->maxRetries,
                        ),
                        $e,
                    );
                }
                $this->backoff($attempt);
            }
        }
        // Unreachable in practice — the loop above either returns or throws.
        throw new DanipaNetworkError('Danipa SDK: retry loop exited unexpectedly', $lastError);
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
            $filtered[$key] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }
        if ($filtered !== []) {
            $url .= '?' . http_build_query($filtered, '', '&', PHP_QUERY_RFC3986);
        }
        return $url;
    }

    /** @return array<string, mixed> */
    private function parseBody(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        if ($body === '') {
            return [];
        }
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new DanipaNetworkError('Danipa SDK: failed to parse response body', $e);
        }
        if (!is_array($decoded)) {
            throw new DanipaNetworkError('Danipa SDK: unexpected non-object response body');
        }
        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function toApiError(ResponseInterface $response): DanipaApiError
    {
        $status = $response->getStatusCode();
        $rawText = (string) $response->getBody();
        $envelope = null;
        if ($rawText !== '') {
            try {
                $decoded = json_decode($rawText, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $envelope = $decoded;
                }
            } catch (\JsonException) {
                // Non-JSON body — fall through with envelope=null.
            }
        }
        $code = 'UNKNOWN';
        $message = sprintf('HTTP %d', $status);
        $correlationId = null;
        if ($envelope !== null) {
            $errors = $envelope['errors'] ?? null;
            if (is_array($errors) && isset($errors[0]) && is_array($errors[0])) {
                $first = $errors[0];
                if (isset($first['code']) && is_string($first['code'])) {
                    $code = $first['code'];
                }
                if (isset($first['description']) && is_string($first['description'])) {
                    $message = $first['description'];
                }
            }
            if (isset($envelope['correlationId']) && is_string($envelope['correlationId'])) {
                $correlationId = $envelope['correlationId'];
            }
        }
        $raw = $envelope ?? ($rawText !== '' ? $rawText : null);
        return new DanipaApiError($status, $code, $message, $correlationId, $raw);
    }

    /** Exponential backoff with jitter — 200 ms, 400 ms, 800 ms (cap 2 s). */
    private function backoff(int $attempt): void
    {
        $base = min(self::BACKOFF_BASE_MS << ($attempt - 1), self::BACKOFF_CAP_MS);
        $jitter = random_int(0, self::BACKOFF_JITTER_MS);
        $delayMs = $base + $jitter;
        if ($this->sleeper !== null) {
            ($this->sleeper)($delayMs);
            return;
        }
        usleep($delayMs * 1000);
    }
}
