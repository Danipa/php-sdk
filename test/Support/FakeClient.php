<?php

declare(strict_types=1);

namespace Danipa\Sdk\Tests\Support;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Test-only PSR-18 client. Records every outbound request and replays a
 * scripted queue of responses (or transport exceptions) in FIFO order.
 */
final class FakeClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    private array $sent = [];

    /** @var list<ResponseInterface|ClientExceptionInterface> */
    private array $queue = [];

    public function reply(int $status, ?string $body = null, string $contentType = 'application/json'): self
    {
        $factory = new HttpFactory();
        $stream = $factory->createStream($body ?? '');
        $response = new Response($status, ['Content-Type' => $contentType], $stream);
        $this->queue[] = $response;
        return $this;
    }

    public function throwTransportError(string $message = 'connection reset'): self
    {
        $this->queue[] = new TransportException($message);
        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->sent[] = $request;
        if ($this->queue === []) {
            throw new TransportException('FakeClient: no scripted response — test setup bug');
        }
        $next = array_shift($this->queue);
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
        if ($this->sent === []) {
            throw new \LogicException('FakeClient: no requests recorded yet');
        }
        return $this->sent[count($this->sent) - 1];
    }
}
