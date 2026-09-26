<?php

declare(strict_types=1);

namespace Danipa\Sdk;

/**
 * A verified webhook delivery, returned by {@see DanipaWebhook::constructEvent()}.
 */
final class WebhookEvent
{
    /**
     * @param string               $type       Event type, from the `X-Danipa-Event` header (e.g. `payment.completed`).
     * @param string               $deliveryId Delivery id, from `X-Danipa-Delivery`; dedupe on it.
     * @param int                  $timestamp  Signing time (epoch seconds), from `X-Danipa-Timestamp`.
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
    }
}
