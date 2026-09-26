<?php

declare(strict_types=1);

namespace Danipa\Sdk\Model;

use Danipa\Sdk\Internal\Money;

/**
 * Body of `POST /v1/merchants/me/payment-links`, mirroring the server's
 * `CreatePaymentLinkRequest`. Optional fields left null are omitted; an
 * omitted currency means "the merchant's billing currency" and an omitted
 * `isAmountFixed` means true.
 */
final class CreatePaymentLinkRequest
{
    public readonly ?string $amount;
    public readonly ?string $minAmount;
    public readonly ?string $maxAmount;

    /**
     * @param string                $title         Required, max 200 characters.
     * @param string|null           $description   Max 2000 characters.
     * @param string|int|float|null $amount        Fixed amount (>= 0.01, 2 dp). Prefer a string.
     * @param string|null           $currency      ISO-4217; omit to use the merchant's billing currency.
     * @param bool|null             $isAmountFixed False lets the payer choose within [minAmount, maxAmount].
     * @param string|null           $redirectUrl   Where the payer lands after paying (max 500 characters).
     * @param string|null           $expiresAt     ISO-8601 offset date-time.
     * @param string|null           $customerId    UUID of an existing customer.
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $description = null,
        string|int|float|null $amount = null,
        public readonly ?string $currency = null,
        public readonly ?bool $isAmountFixed = null,
        string|int|float|null $minAmount = null,
        string|int|float|null $maxAmount = null,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $expiresAt = null,
        public readonly ?string $customerId = null,
    ) {
        if (trim($title) === '') {
            throw new \InvalidArgumentException('Danipa SDK: title is required');
        }
        $this->amount = $amount === null ? null : Money::normalize($amount);
        $this->minAmount = $minAmount === null ? null : Money::normalize($minAmount);
        $this->maxAmount = $maxAmount === null ? null : Money::normalize($maxAmount);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $out = ['title' => $this->title];
        foreach (
            [
                'customerId' => $this->customerId,
                'description' => $this->description,
                'amount' => $this->amount,
                'currency' => $this->currency,
                'isAmountFixed' => $this->isAmountFixed,
                'minAmount' => $this->minAmount,
                'maxAmount' => $this->maxAmount,
                'redirectUrl' => $this->redirectUrl,
                'expiresAt' => $this->expiresAt,
            ] as $key => $value
        ) {
            if ($value !== null) {
                $out[$key] = $value;
            }
        }
        return $out;
    }
}
