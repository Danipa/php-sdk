<?php

declare(strict_types=1);

namespace Danipa\Sdk\Tests\Support;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;

/** PSR-18 "the request itself is unsendable" failure — never worth retrying. */
final class InvalidRequestException extends \RuntimeException implements RequestExceptionInterface
{
    public function getRequest(): RequestInterface
    {
        return new Request('GET', 'https://example.invalid');
    }
}
