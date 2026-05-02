<?php

declare(strict_types=1);

namespace Danipa\Sdk\Model;

final class InvoiceLineItem
{
    public function __construct(
        public readonly string $description,
        public readonly int $quantity,
        public readonly string $unitPrice,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            description: (string) $data['description'],
            quantity: (int) $data['quantity'],
            unitPrice: (string) $data['unitPrice'],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unitPrice' => $this->unitPrice,
        ];
    }
}
