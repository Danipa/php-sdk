<?php

declare(strict_types=1);

namespace Danipa\Sdk\Model;

final class CreatePaymentLinkRequest
{
    /**
     * @param string      $amount      Decimal-as-string.
     * @param string      $currency    ISO-4217.
     * @param string|null $description Optional shopper-visible description.
     * @param string|null $expiresAt   ISO-8601 expiry; null falls back to backend default.
     */
    public function __construct(
        public readonly string $amount,
        public readonly string $currency,
        public readonly ?string $description = null,
        public readonly ?string $expiresAt = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $out = [
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
        if ($this->description !== null) {
            $out['description'] = $this->description;
        }
        if ($this->expiresAt !== null) {
            $out['expiresAt'] = $this->expiresAt;
        }
        return $out;
    }
}
