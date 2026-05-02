<?php

declare(strict_types=1);

namespace Danipa\Sdk\Model;

final class Disbursement
{
    public function __construct(
        public readonly string $id,
        public readonly string $merchantId,
        public readonly string $amount,
        public readonly string $currency,
        public readonly DisbursementStatus $status,
        public readonly Recipient $recipient,
        public readonly string $createdAt,
        public readonly ?string $completedAt,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $recipientArr = $data['recipient'] ?? [];
        \assert(\is_array($recipientArr));
        return new self(
            id: (string) $data['id'],
            merchantId: (string) $data['merchantId'],
            amount: (string) $data['amount'],
            currency: (string) $data['currency'],
            status: DisbursementStatus::from((string) $data['status']),
            recipient: Recipient::fromArray($recipientArr),
            createdAt: (string) $data['createdAt'],
            completedAt: isset($data['completedAt']) ? (string) $data['completedAt'] : null,
        );
    }
}
