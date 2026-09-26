<?php

declare(strict_types=1);

namespace Danipa\Sdk;

/**
 * Per-call overrides.
 *
 * Every mutating call (POST/PUT/PATCH/DELETE) is sent with an
 * `Idempotency-Key`. When you pass none, the SDK generates a UUIDv4 and reuses
 * it for every retry of that call, so a retried request can never act twice.
 * Pass your own key (e.g. derived from your order id) to make the call
 * idempotent across processes and restarts too:
 *
 * ```php
 * $danipa->paymentLinks()->create($req, RequestOptions::withIdempotencyKey('order-1234'));
 * ```
 *
 * Keys are at most 255 characters (the server's limit); longer keys are
 * rejected before anything is sent. Instances are immutable.
 */
final class RequestOptions
{
    public const MAX_IDEMPOTENCY_KEY_LENGTH = 255;

    public function __construct(
        public readonly ?string $idempotencyKey = null,
    ) {
        if ($idempotencyKey !== null) {
            self::assertValidKey($idempotencyKey);
        }
    }

    public static function none(): self
    {
        return new self();
    }

    public static function withIdempotencyKey(string $key): self
    {
        return new self(idempotencyKey: $key);
    }

    /** @internal */
    public static function assertValidKey(string $key): void
    {
        if (trim($key) === '') {
            throw new \InvalidArgumentException('Danipa SDK: idempotency key must not be blank');
        }
        if (\strlen($key) > self::MAX_IDEMPOTENCY_KEY_LENGTH) {
            throw new \InvalidArgumentException(sprintf(
                'Danipa SDK: idempotency key must be at most %d characters, got %d',
                self::MAX_IDEMPOTENCY_KEY_LENGTH,
                \strlen($key),
            ));
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $key) === 1) {
            throw new \InvalidArgumentException('Danipa SDK: idempotency key must not contain control characters');
        }
    }
}
