<?php

declare(strict_types=1);

namespace Danipa\Sdk\Model;

use Danipa\Sdk\Internal\Json;
use Danipa\Sdk\ResponseMeta;

/**
 * A hosted payment link — the server's full `PaymentLinkResponse`. Money
 * fields are decimal strings (parsed exactly from the JSON number the server
 * writes). Timestamps are ISO-8601 strings.
 */
final class PaymentLink
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $customerId,
        public readonly ?string $shortCode,
        public readonly ?string $url,
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly ?string $amount,
        public readonly ?string $currency,
        public readonly bool $isAmountFixed,
        public readonly ?string $minAmount,
        public readonly ?string $maxAmount,
        public readonly PaymentLinkStatus $status,
        public readonly ?string $statusRaw,
        public readonly ?string $redirectUrl,
        public readonly ?string $expiresAt,
        public readonly ?string $totalCollected,
        public readonly int $paymentCount,
        public readonly ?string $createdAt,
        public readonly ?string $merchantName,
        public readonly ?string $merchantLogoUrl,
        public readonly ?string $merchantBrandColor,
        public readonly ?string $merchantStorefrontUrl,
        public readonly ?ResponseMeta $meta = null,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     * @throws \Danipa\Sdk\Internal\MalformedResponse
     * @internal
     */
    public static function fromArray(array $data, ?ResponseMeta $meta = null): self
    {
        $status = Json::optString($data, 'status');
        return new self(
            id: Json::string($data, 'id'),
            customerId: Json::optString($data, 'customerId'),
            shortCode: Json::optString($data, 'shortCode'),
            url: Json::optString($data, 'url'),
            title: Json::optString($data, 'title'),
            description: Json::optString($data, 'description'),
            amount: Json::optDecimal($data, 'amount'),
            currency: Json::optString($data, 'currency'),
            isAmountFixed: Json::bool($data, 'isAmountFixed', true),
            minAmount: Json::optDecimal($data, 'minAmount'),
            maxAmount: Json::optDecimal($data, 'maxAmount'),
            status: PaymentLinkStatus::parse($status),
            statusRaw: $status,
            redirectUrl: Json::optString($data, 'redirectUrl'),
            expiresAt: Json::optString($data, 'expiresAt'),
            totalCollected: Json::optDecimal($data, 'totalCollected'),
            paymentCount: Json::optInt($data, 'paymentCount') ?? 0,
            createdAt: Json::optString($data, 'createdAt'),
            merchantName: Json::optString($data, 'merchantName'),
            merchantLogoUrl: Json::optString($data, 'merchantLogoUrl'),
            merchantBrandColor: Json::optString($data, 'merchantBrandColor'),
            merchantStorefrontUrl: Json::optString($data, 'merchantStorefrontUrl'),
            meta: $meta,
        );
    }
}
