<?php

declare(strict_types=1);

namespace Danipa\Sdk\Model;

final class Money
{
    /**
     * @param string $amount   Decimal as string to preserve precision (e.g. "125.00").
     * @param string $currency ISO-4217 code (e.g. "GHS", "USD").
     */
    public function __construct(
        public readonly string $amount,
        public readonly string $currency,
    ) {
    }

    /** @param array{amount: string, currency: string} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            amount: $data['amount'],
            currency: $data['currency'],
        );
    }

    /** @return array{amount: string, currency: string} */
    public function toArray(): array
    {
        return ['amount' => $this->amount, 'currency' => $this->currency];
    }
}
