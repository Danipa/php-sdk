<?php

declare(strict_types=1);

namespace Danipa\Sdk;

use Danipa\Sdk\Http\HttpClient;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Fluent builder for {@see Danipa}. See {@see Danipa::builder()}.
 *
 * Transport: pass any PSR-18 client with {@see transport()} (and optionally
 * PSR-17 factories). Anything not injected is discovered with
 * `php-http/discovery` — install e.g. `guzzlehttp/guzzle` or
 * `symfony/http-client` + `nyholm/psr7`.
 *
 * Timeouts belong to the client: the SDK cannot set a timeout on an
 * arbitrary PSR-18 client, so configure it where you construct the client
 * (e.g. `new \GuzzleHttp\Client(['timeout' => 30, 'connect_timeout' => 10])`).
 */
final class DanipaBuilder
{
    private ?string $apiKey = null;
    private ?string $baseUrl = null;
    private int $maxRetries = HttpClient::DEFAULT_MAX_RETRIES;
    private ?ClientInterface $transport = null;
    private ?RequestFactoryInterface $requestFactory = null;
    private ?StreamFactoryInterface $streamFactory = null;

    /** Required. `dk_test_…` (sandbox) or `dk_live_…` (production, not yet live). */
    public function apiKey(string $apiKey): self
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    /** Optional override; by default inferred from the API key prefix. */
    public function baseUrl(string $baseUrl): self
    {
        $this->baseUrl = $baseUrl;
        return $this;
    }

    /**
     * Number of RETRIES after the first attempt (0 disables retrying).
     * Default 2. Only 408/429/502/503/504 and connection failures are retried.
     */
    public function maxRetries(int $maxRetries): self
    {
        $this->maxRetries = $maxRetries;
        return $this;
    }

    /** Optional PSR-18 client; discovered when omitted. Configure timeouts on it. */
    public function transport(ClientInterface $transport): self
    {
        $this->transport = $transport;
        return $this;
    }

    /** Optional PSR-17 request factory; discovered when omitted. */
    public function requestFactory(RequestFactoryInterface $factory): self
    {
        $this->requestFactory = $factory;
        return $this;
    }

    /** Optional PSR-17 stream factory; discovered when omitted. */
    public function streamFactory(StreamFactoryInterface $factory): self
    {
        $this->streamFactory = $factory;
        return $this;
    }

    public function build(): Danipa
    {
        if ($this->apiKey === null || trim($this->apiKey) === '') {
            throw new \InvalidArgumentException('Danipa SDK: apiKey is required');
        }
        return Danipa::fromHttpClient(new HttpClient(
            apiKey: $this->apiKey,
            baseUrl: $this->baseUrl,
            maxRetries: $this->maxRetries,
            client: $this->transport,
            requestFactory: $this->requestFactory,
            streamFactory: $this->streamFactory,
        ));
    }
}
