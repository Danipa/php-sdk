<?php

declare(strict_types=1);

namespace Danipa\Sdk\Model;

use Danipa\Sdk\Internal\Money;

/**
 * Body of `PUT /v1/merchants/me/payment-links/{id}`, mirroring the server's
 * `UpdatePaymentLinkRequest`. It is a partial update: every field is optional,
 * and a field left null is omitted and keeps its current value on the server.
 * Currency and customer cannot be changed. Only an `ACTIVE` link can be updated.
 */
final class UpdatePaymentLinkRequest
{
    public readonly ?string $amount;
    public readonly ?string $minAmount;
    public readonly ?string $maxAmount;

    /**
     * @param string|null           $title         Max 200 characters.
     * @param string|null           $description   Max 2000 characters.
     * @param string|int|float|null $amount        >= 0.01, 2 dp. Prefer a string.
     * @param bool|null             $isAmountFixed False lets the payer choose within [minAmount, maxAmount].
     * @param string|null           $redirectUrl   Max 500 characters.
     * @param string|null           $expiresAt     ISO-8601 offset date-time.
     */
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        string|int|float|null $amount = null,
        public readonly ?bool $isAmountFixed = null,
        string|int|float|null $minAmount = null,
        string|int|float|null $maxAmount = null,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $expiresAt = null,
    ) {
        if ($title !== null && trim($title) === '') {
            throw new \InvalidArgumentException(
                'Danipa SDK: title must not be blank; omit it to keep the current title',
            );
        }
        $this->amount = $amount === null ? null : Money::normalize($amount);
        $this->minAmount = $minAmount === null ? null : Money::normalize($minAmount);
        $this->maxAmount = $maxAmount === null ? null : Money::normalize($maxAmount);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $out = [];
        foreach (
            [
                'title' => $this->title,
                'description' => $this->description,
                'amount' => $this->amount,
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
