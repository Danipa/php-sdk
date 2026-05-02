<?php

declare(strict_types=1);

namespace Danipa\Sdk\Model;

final class PaymentLink
{
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly string $amount,
        public readonly string $currency,
        public readonly ?string $description,
        public readonly ?string $expiresAt,
        public readonly string $createdAt,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            url: (string) $data['url'],
            amount: (string) $data['amount'],
            currency: (string) $data['currency'],
            description: isset($data['description']) ? (string) $data['description'] : null,
            expiresAt: isset($data['expiresAt']) ? (string) $data['expiresAt'] : null,
            createdAt: (string) $data['createdAt'],
        );
    }
}
