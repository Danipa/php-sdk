<?php

declare(strict_types=1);

namespace Danipa\Sdk;

/**
 * Wire facts about the response a model was built from. Every model a
 * resource returns carries one on its `$meta` property.
 */
final class ResponseMeta
{
    public function __construct(
        /** HTTP status of the final attempt. */
        public readonly int $status,
        /** `X-Correlation-Id` / `X-Request-Id` header, else body `correlationId` / `traceId`. */
        public readonly ?string $requestId = null,
        /** The `Idempotency-Key` the SDK sent (caller-supplied or auto-generated); null on reads. */
        public readonly ?string $idempotencyKey = null,
        /** True when the server replayed a cached result (`X-Idempotent-Replay: true`). */
        public readonly bool $idempotentReplay = false,
        /** Number of attempts made, 1 = no retry. */
        public readonly int $attempts = 1,
    ) {
    }
}
