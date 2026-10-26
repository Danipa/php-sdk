<?php

declare(strict_types=1);

namespace Danipa\Sdk;

/**
 * A verified webhook delivery, returned by {@see DanipaWebhook::constructEvent()}.
 *
 * Dedupe on {@see self::$dedupeKey} (or the body's own id, where the payload has
 * one) and branch on the body's own event type where present. `$type` and
 * `$deliveryId` come from UNSIGNED headers and are informational only.
 */
final class WebhookEvent
{
    /**
     * Lowercase-hex SHA-256 of the verified raw body bytes. Danipa re-signs every
     * retry with a fresh timestamp over the same body, so this is identical across
     * retries of the same delivery: dedupe on it. It identifies a body, not a
     * delivery: a dashboard replay (same body) and two distinct events with
     * byte-identical bodies share it, so prefer the body's own unique id where
     * it has one.
     */
    public readonly string $dedupeKey;

    /**
     * @param string               $type       Event type, from the UNSIGNED `X-Danipa-Event` header
     *                                         (e.g. `payment.completed`). Informational only.
     * @param string               $deliveryId Delivery id, from the UNSIGNED `X-Danipa-Delivery` header.
     *                                         Informational only; do not dedupe on it.
     * @param int                  $timestamp  Signing time (epoch seconds), from `X-Danipa-Timestamp`;
     *                                         differs per retry.
     * @param array<string, mixed> $data       The decoded JSON body. Decimal numbers arrive as exact strings.
     * @param string               $payload    The raw body bytes that were verified.
     */
    public function __construct(
        public readonly string $type,
        public readonly string $deliveryId,
        public readonly int $timestamp,
        public readonly array $data,
        public readonly string $payload,
    ) {
        $this->dedupeKey = hash('sha256', $payload);
    }
}
