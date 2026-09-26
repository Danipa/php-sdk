<?php

declare(strict_types=1);

namespace Danipa\Sdk\Model;

use Danipa\Sdk\Internal\Json;

/**
 * A line on an issued invoice (server `Invoice.LineItem`). Money is a decimal string.
 *
 * `description`, `quantity` and `unitPrice` are always set by every server path
 * that builds a line (connector imports default the description to
 * "(unspecified)"), so their absence is a malformed response. `lineTotal` is
 * nullable for lines persisted before it existed; `taxRatePct` is optional.
 */
final class InvoiceLineItem
{
    public function __construct(
        public readonly string $description,
        public readonly int $quantity,
        public readonly string $unitPrice,
        public readonly ?string $lineTotal = null,
        public readonly ?string $taxRatePct = null,
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
            description: Json::string($data, 'description'),
            quantity: Json::int($data, 'quantity'),
            unitPrice: Json::decimal($data, 'unitPrice'),
            lineTotal: Json::optDecimal($data, 'lineTotal'),
            taxRatePct: Json::optDecimal($data, 'taxRatePct'),
        );
    }
}
