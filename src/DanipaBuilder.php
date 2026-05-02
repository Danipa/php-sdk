<?php

declare(strict_types=1);

namespace Danipa\Sdk;

use Danipa\Sdk\Http\HttpClient;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Fluent builder for {@see Danipa}. See {@see Danipa::builder()}.
 */
final class DanipaBuilder
{
    private ?string $apiKey = null;
    private ?string $baseUrl = null;
    private int $timeoutMs = HttpClient::DEFAULT_TIMEOUT_MS;
    private int $maxRetries = HttpClient::DEFAULT_MAX_RETRIES;
    private ?ClientInterface $transport = null;
    private ?RequestFactoryInterface $requestFactory = null;
    private ?StreamFactoryInterface $streamFactory = null;

    /** Required. Starts with `dk_test_` (sandbox) or `dk_live_` (production). */
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

    /** Optional. Default 30 000 ms (30 s). */
    public function timeoutMs(int $timeoutMs): self
    {
        $this->timeoutMs = $timeoutMs;
        return $this;
    }

    /** Optional. Default 3 (= 1 try + 2 retries on 5xx / network errors). */
    public function maxRetries(int $maxRetries): self
    {
        $this->maxRetries = $maxRetries;
        return $this;
    }

    /** Optional PSR-18 client. Defaults to a Guzzle 7 client. */
    public function transport(ClientInterface $transport): self
    {
        $this->transport = $transport;
        return $this;
    }

    /** Optional PSR-17 request factory. Defaults to Guzzle's `HttpFactory`. */
    public function requestFactory(RequestFactoryInterface $factory): self
    {
        $this->requestFactory = $factory;
        return $this;
    }

    /** Optional PSR-17 stream factory. Defaults to Guzzle's `HttpFactory`. */
    public function streamFactory(StreamFactoryInterface $factory): self
    {
        $this->streamFactory = $factory;
        return $this;
    }

    public function build(): Danipa
    {
        if ($this->apiKey === null || $this->apiKey === '') {
            throw new \InvalidArgumentException('Danipa SDK: apiKey is required');
        }
        $http = new HttpClient(
            apiKey: $this->apiKey,
            baseUrl: $this->baseUrl,
            timeoutMs: $this->timeoutMs,
            maxRetries: $this->maxRetries,
            client: $this->transport,
            requestFactory: $this->requestFactory,
            streamFactory: $this->streamFactory,
        );
        return Danipa::fromHttpClient($http);
    }
}
