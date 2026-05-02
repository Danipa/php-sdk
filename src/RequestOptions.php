<?php

declare(strict_types=1);

namespace Danipa\Sdk;

/**
 * Per-call overrides. Pass `RequestOptions::withIdempotencyKey(...)` on every
 * mutating call — retries (server-side or client-side via this SDK) won't
 * double-charge because the backend dedupes on `(merchantId, X-Idempotency-Key)`.
 *
 * ```php
 * $danipa->collections()->create($req, RequestOptions::withIdempotencyKey('order-1234'));
 * ```
 *
 * Instances are immutable — the `with*` methods return a new instance.
 */
final class RequestOptions
{
    public function __construct(
        public readonly ?string $idempotencyKey = null,
    ) {
    }

    public static function none(): self
    {
        return new self();
    }

    public static function withIdempotencyKey(string $key): self
    {
        return new self(idempotencyKey: $key);
    }
}
