<?php

declare(strict_types=1);

namespace Danipa\Sdk\Errors;

/**
 * HTTP 429 (`RATE_LIMIT_EXCEEDED`) after the SDK's retry budget was spent.
 * `$retryAfter` is the server's `Retry-After` in seconds (header, else the
 * envelope's `retryAfterSeconds`), or null when it sent none.
 */
final class RateLimitError extends DanipaApiError
{
    public function retryAfter(): ?int
    {
        return $this->retryAfterSeconds;
    }
}
