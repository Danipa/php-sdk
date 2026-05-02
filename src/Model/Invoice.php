<?php

declare(strict_types=1);

namespace Danipa\Sdk\Model;

final class Invoice
{
    /**
     * @param list<InvoiceLineItem> $lineItems
     */
    public function __construct(
        public readonly string $id,
        public readonly string $merchantId,
        public readonly string $amount,
        public readonly string $currency,
        public readonly string $dueDate,
        public readonly InvoiceStatus $status,
        public readonly string $customerEmail,
        public readonly array $lineItems,
        public readonly string $createdAt,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $rawItems = $data['lineItems'] ?? [];
        \assert(\is_array($rawItems));
        $items = [];
        foreach ($rawItems as $item) {
            \assert(\is_array($item));
            $items[] = InvoiceLineItem::fromArray($item);
        }
        return new self(
            id: (string) $data['id'],
            merchantId: (string) $data['merchantId'],
            amount: (string) $data['amount'],
            currency: (string) $data['currency'],
            dueDate: (string) $data['dueDate'],
            status: InvoiceStatus::from((string) $data['status']),
            customerEmail: (string) $data['customerEmail'],
            lineItems: $items,
            createdAt: (string) $data['createdAt'],
        );
    }
}
