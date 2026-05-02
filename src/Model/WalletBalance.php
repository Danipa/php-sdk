<?php

declare(strict_types=1);

namespace Danipa\Sdk\Model;

final class WalletBalance
{
    public function __construct(
        public readonly string $merchantId,
        public readonly string $currency,
        public readonly string $available,
        public readonly string $pending,
        public readonly string $asOf,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            merchantId: (string) $data['merchantId'],
            currency: (string) $data['currency'],
            available: (string) $data['available'],
            pending: (string) $data['pending'],
            asOf: (string) $data['asOf'],
        );
    }
}
