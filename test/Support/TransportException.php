<?php

declare(strict_types=1);

namespace Danipa\Sdk\Tests\Support;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

/** PSR-18 network failure (no response): what Guzzle's ConnectException is. */
final class TransportException extends \RuntimeException implements NetworkExceptionInterface
{
    public function getRequest(): RequestInterface
    {
        return new Request('GET', 'https://example.invalid');
    }
}
