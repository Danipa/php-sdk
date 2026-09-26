<?php

declare(strict_types=1);

namespace Danipa\Sdk\Model;

use Danipa\Sdk\Internal\Json;

/** One tax component of a province breakdown, e.g. `GST 5%` (server `Invoice.TaxLine`). */
final class InvoiceTaxLine
{
    public function __construct(
        public readonly ?string $label,
        /** Percentage, e.g. "5.00" (not "0.05"). */
        public readonly ?string $ratePct,
        public readonly ?string $amount,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     * @throws \Danipa\Sdk\Internal\MalformedResponse
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            label: Json::optString($data, 'label'),
            ratePct: Json::optDecimal($data, 'ratePct'),
            amount: Json::optDecimal($data, 'amount'),
        );
    }
}
