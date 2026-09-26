<?php

declare(strict_types=1);

namespace Danipa\Sdk\Model;

use Danipa\Sdk\Internal\Money;

/** A line on {@see CreateInvoiceRequest} (server `CreateInvoiceRequest.LineItemRequest`). */
final class InvoiceLineItemRequest
{
    public readonly string $unitPrice;
    public readonly ?string $taxRatePct;

    /**
     * @param string           $description Required, non-blank.
     * @param int              $quantity    Must be positive.
     * @param string|int|float $unitPrice   At least 0.01, max 2 decimal places. Prefer a string.
     * @param string|int|float|null $taxRatePct Optional per-line tax rate in percent (e.g. "13").
     */
    public function __construct(
        public readonly string $description,
        public readonly int $quantity,
        string|int|float $unitPrice,
        string|int|float|null $taxRatePct = null,
    ) {
        $this->unitPrice = Money::normalize($unitPrice);
        $this->taxRatePct = $taxRatePct === null ? null : Money::normalize($taxRatePct);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $out = [
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unitPrice' => $this->unitPrice,
        ];
        if ($this->taxRatePct !== null) {
            $out['taxRatePct'] = $this->taxRatePct;
        }
        return $out;
    }
}
