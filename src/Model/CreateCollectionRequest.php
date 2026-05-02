<?php

declare(strict_types=1);

namespace Danipa\Sdk\Model;

final class CreateCollectionRequest
{
    public function __construct(
        public readonly string $amount,
        public readonly string $currency,
        public readonly Payer $payer,
        public readonly ?string $description = null,
        public readonly ?string $reference = null,
        public readonly ?string $paymentLinkId = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $out = [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payer' => $this->payer->toArray(),
        ];
        if ($this->description !== null) {
            $out['description'] = $this->description;
        }
        if ($this->reference !== null) {
            $out['reference'] = $this->reference;
        }
        if ($this->paymentLinkId !== null) {
            $out['paymentLinkId'] = $this->paymentLinkId;
        }
        return $out;
    }
}
