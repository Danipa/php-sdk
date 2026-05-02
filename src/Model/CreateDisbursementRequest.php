<?php

declare(strict_types=1);

namespace Danipa\Sdk\Model;

final class CreateDisbursementRequest
{
    public function __construct(
        public readonly string $amount,
        public readonly string $currency,
        public readonly Recipient $recipient,
        public readonly ?string $description = null,
        public readonly ?string $reference = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $out = [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'recipient' => $this->recipient->toArray(),
        ];
        if ($this->description !== null) {
            $out['description'] = $this->description;
        }
        if ($this->reference !== null) {
            $out['reference'] = $this->reference;
        }
        return $out;
    }
}
