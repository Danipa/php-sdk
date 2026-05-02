<?php

declare(strict_types=1);

namespace Danipa\Sdk\Model;

final class Collection
{
    public function __construct(
        public readonly string $id,
        public readonly string $merchantId,
        public readonly string $amount,
        public readonly string $currency,
        public readonly CollectionStatus $status,
        public readonly Payer $payer,
        public readonly ?string $paymentLinkId,
        public readonly string $createdAt,
        public readonly ?string $completedAt,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $payerArr = $data['payer'] ?? [];
        \assert(\is_array($payerArr));
        return new self(
            id: (string) $data['id'],
            merchantId: (string) $data['merchantId'],
            amount: (string) $data['amount'],
            currency: (string) $data['currency'],
            status: CollectionStatus::from((string) $data['status']),
            payer: Payer::fromArray($payerArr),
            paymentLinkId: isset($data['paymentLinkId']) ? (string) $data['paymentLinkId'] : null,
            createdAt: (string) $data['createdAt'],
            completedAt: isset($data['completedAt']) ? (string) $data['completedAt'] : null,
        );
    }
}
