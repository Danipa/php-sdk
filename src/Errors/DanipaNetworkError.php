<?php

declare(strict_types=1);

namespace Danipa\Sdk\Errors;

/**
 * Thrown when the request never reached the backend (DNS, TLS, abort, timeout,
 * exhausted retry budget on 5xx). Wraps the underlying transport exception via
 * `getPrevious()`.
 */
final class DanipaNetworkError extends DanipaError
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
