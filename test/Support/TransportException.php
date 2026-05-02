<?php

declare(strict_types=1);

namespace Danipa\Sdk\Tests\Support;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

/**
 * PSR-18 NetworkExceptionInterface stub for tests. Real Guzzle would throw
 * `GuzzleHttp\Exception\ConnectException` here; we use a hand-rolled stub so
 * the test layer doesn't depend on Guzzle internals.
 */
final class TransportException extends \RuntimeException implements NetworkExceptionInterface
{
    public function getRequest(): RequestInterface
    {
        // Tests don't inspect the request — Guzzle MockHandler doesn't either.
        throw new \LogicException('TransportException::getRequest is not supported in this test stub');
    }
}
