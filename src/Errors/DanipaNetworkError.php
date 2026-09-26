<?php

declare(strict_types=1);

namespace Danipa\Sdk\Errors;

/**
 * No response was received (DNS, TLS, connection reset, timeout) — after the
 * retry budget was spent. Thrown as {@see ConnectionError} or
 * {@see TimeoutError}; the transport exception is on `getPrevious()`.
 *
 * A mutating call may still have reached the server. Retry it with the SAME
 * `$idempotencyKey` so the server can dedupe.
 */
abstract class DanipaNetworkError extends DanipaError
{
    public function __construct(
        string $message,
        ?\Throwable $previous = null,
        public readonly ?string $idempotencyKey = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
