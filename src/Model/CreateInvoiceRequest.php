<?php

declare(strict_types=1);

namespace Danipa\Sdk\Model;

use Danipa\Sdk\Internal\Money;

/**
 * Body of `POST /v1/merchants/me/invoices`, mirroring the server's
 * `CreateInvoiceRequest`. Optional fields left null are omitted from the
 * request; an omitted currency means "the merchant's billing currency".
 */
final class CreateInvoiceRequest
{
    public readonly ?string $taxAmount;

    /**
     * @param string                       $customerName  Required, max 200 characters.
     * @param list<InvoiceLineItemRequest> $lineItems     1–200 lines.
     * @param string|null                  $customerEmail Max 200 characters.
     * @param string|null                  $customerPhone Max 20 characters.
     * @param string|null                  $customerId    UUID of an existing customer.
     * @param string|int|float|null        $taxAmount     Non-negative; prefer a string.
     * @param string|null                  $currency      ISO-4217; omit to use the merchant's billing currency.
     * @param string|null                  $dueDate       ISO date, `YYYY-MM-DD`.
     */
    public function __construct(
        public readonly string $customerName,
        public readonly array $lineItems,
        public readonly ?string $customerEmail = null,
        public readonly ?string $customerPhone = null,
        public readonly ?string $customerId = null,
        string|int|float|null $taxAmount = null,
        public readonly ?string $currency = null,
        public readonly ?string $dueDate = null,
        public readonly ?string $notes = null,
    ) {
        if (trim($customerName) === '') {
            throw new \InvalidArgumentException('Danipa SDK: customerName is required');
        }
        if ($lineItems === []) {
            throw new \InvalidArgumentException('Danipa SDK: an invoice needs at least one line item');
        }
        $this->taxAmount = $taxAmount === null ? null : Money::normalize($taxAmount);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $out = ['customerName' => $this->customerName];
        foreach (
            [
                'customerId' => $this->customerId,
                'customerEmail' => $this->customerEmail,
                'customerPhone' => $this->customerPhone,
            ] as $key => $value
        ) {
            if ($value !== null) {
                $out[$key] = $value;
            }
        }
        $out['lineItems'] = array_map(static fn (InvoiceLineItemRequest $l): array => $l->toArray(), $this->lineItems);
        foreach (
            [
                'taxAmount' => $this->taxAmount,
                'currency' => $this->currency,
                'dueDate' => $this->dueDate,
                'notes' => $this->notes,
            ] as $key => $value
        ) {
            if ($value !== null) {
                $out[$key] = $value;
            }
        }
        return $out;
    }
}
