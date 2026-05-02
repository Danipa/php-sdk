<?php

declare(strict_types=1);

namespace Danipa\Sdk\Model;

final class CreateInvoiceRequest
{
    /**
     * @param list<InvoiceLineItem> $lineItems
     */
    public function __construct(
        public readonly string $customerEmail,
        public readonly string $currency,
        public readonly string $dueDate,
        public readonly array $lineItems,
        public readonly ?string $notes = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $items = [];
        foreach ($this->lineItems as $item) {
            $items[] = $item->toArray();
        }
        $out = [
            'customerEmail' => $this->customerEmail,
            'currency' => $this->currency,
            'dueDate' => $this->dueDate,
            'lineItems' => $items,
        ];
        if ($this->notes !== null) {
            $out['notes'] = $this->notes;
        }
        return $out;
    }
}
