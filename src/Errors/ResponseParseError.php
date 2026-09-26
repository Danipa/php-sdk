<?php

declare(strict_types=1);

namespace Danipa\Sdk\Errors;

/**
 * The server answered 2xx but the body could not be parsed into the expected
 * model. This is NOT a network error: the call most likely SUCCEEDED. Do not
 * blindly retry a mutating call; if you must, reuse `$idempotencyKey` so the
 * server replays the original result instead of acting twice.
 */
final class ResponseParseError extends DanipaError
{
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly string $body,
        public readonly ?string $requestId = null,
        public readonly ?string $idempotencyKey = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
