<?php

declare(strict_types=1);

namespace Danipa\Sdk\Tests\Support;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Test-only PSR-18 transport. Records every request exactly as it would go
 * on the wire and replays a scripted FIFO queue of responses or transport
 * exceptions.
 */
final class FakeClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    private array $sent = [];

    /** @var list<ResponseInterface|ClientExceptionInterface> */
    private array $queue = [];

    /** @param array<string, string> $headers */
    public function reply(int $status, string $body = '', array $headers = []): self
    {
        $this->queue[] = new Response($status, $headers + ['Content-Type' => 'application/json'], $body);
        return $this;
    }

    public function fail(ClientExceptionInterface $e): self
    {
        $this->queue[] = $e;
        return $this;
    }

    public function throwTransportError(string $message = 'connection reset'): self
    {
        return $this->fail(new TransportException($message));
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->sent[] = $request;
        $next = array_shift($this->queue);
        if ($next === null) {
            throw new \LogicException('FakeClient: no scripted response left — test setup bug');
        }
        if ($next instanceof ClientExceptionInterface) {
            throw $next;
        }
        return $next;
    }

    /** @return list<RequestInterface> */
    public function sent(): array
    {
        return $this->sent;
    }

    public function lastRequest(): RequestInterface
    {
        $last = $this->sent[\count($this->sent) - 1] ?? null;
        if ($last === null) {
            throw new \LogicException('FakeClient: no requests recorded yet');
        }
        return $last;
    }

    /** @return array<string, mixed> */
    public function lastJsonBody(): array
    {
        $decoded = json_decode((string) $this->lastRequest()->getBody(), true, 512, JSON_THROW_ON_ERROR);
        if (!\is_array($decoded)) {
            throw new \LogicException('FakeClient: last request body is not a JSON object');
        }
        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
