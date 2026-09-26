<?php

declare(strict_types=1);

namespace Danipa\Sdk\Model;

use Danipa\Sdk\Internal\Json;
use Danipa\Sdk\ResponseMeta;

/**
 * The authenticated merchant's profile — the server's `MerchantResponse`
 * from `GET /v1/merchants/me`. The server omits null fields (NON_NULL), so
 * most properties are nullable. Decimal rates are strings; timestamps are
 * ISO-8601 strings.
 */
final class Merchant
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $businessName,
        public readonly ?string $businessType,
        public readonly ?string $registrationNumber,
        public readonly ?string $country,
        public readonly ?string $status,
        public readonly ?string $kybStatus,
        public readonly ?string $tier,
        public readonly ?string $tosAcceptedAt,
        public readonly ?string $tosVersion,
        public readonly ?string $currentTosVersion,
        public readonly ?string $createdAt,
        public readonly ?string $addressLine1,
        public readonly ?string $addressLine2,
        public readonly ?string $city,
        public readonly ?string $region,
        public readonly ?string $postalCode,
        public readonly ?string $contactEmail,
        public readonly ?string $contactPhone,
        public readonly ?string $website,
        public readonly ?string $whatsapp,
        public readonly ?string $slug,
        public readonly bool $publicCatalogEnabled,
        public readonly ?string $taxId,
        public readonly ?string $taxIdType,
        /** The stored house rate; null when never set. */
        public readonly ?string $defaultTaxRatePct,
        /** The rate the server actually applies to a line without its own rate. */
        public readonly ?string $effectiveDefaultTaxRatePct,
        /** The one currency this merchant transacts in (GHS, USD, CAD, ...). */
        public readonly ?string $billingCurrency,
        public readonly ?string $storefrontTheme,
        public readonly ?string $storefrontTemplate,
        public readonly ?string $checkoutMode,
        public readonly ?string $externalCheckoutUrl,
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
        return new self(
            id: Json::string($data, 'id'),
            businessName: Json::optString($data, 'businessName'),
            businessType: Json::optString($data, 'businessType'),
            registrationNumber: Json::optString($data, 'registrationNumber'),
            country: Json::optString($data, 'country'),
            status: Json::optString($data, 'status'),
            kybStatus: Json::optString($data, 'kybStatus'),
            tier: Json::optString($data, 'tier'),
            tosAcceptedAt: Json::optString($data, 'tosAcceptedAt'),
            tosVersion: Json::optString($data, 'tosVersion'),
            currentTosVersion: Json::optString($data, 'currentTosVersion'),
            createdAt: Json::optString($data, 'createdAt'),
            addressLine1: Json::optString($data, 'addressLine1'),
            addressLine2: Json::optString($data, 'addressLine2'),
            city: Json::optString($data, 'city'),
            region: Json::optString($data, 'region'),
            postalCode: Json::optString($data, 'postalCode'),
            contactEmail: Json::optString($data, 'contactEmail'),
            contactPhone: Json::optString($data, 'contactPhone'),
            website: Json::optString($data, 'website'),
            whatsapp: Json::optString($data, 'whatsapp'),
            slug: Json::optString($data, 'slug'),
            publicCatalogEnabled: Json::bool($data, 'publicCatalogEnabled', false),
            taxId: Json::optString($data, 'taxId'),
            taxIdType: Json::optString($data, 'taxIdType'),
            defaultTaxRatePct: Json::optDecimal($data, 'defaultTaxRatePct'),
            effectiveDefaultTaxRatePct: Json::optDecimal($data, 'effectiveDefaultTaxRatePct'),
            billingCurrency: Json::optString($data, 'billingCurrency'),
            storefrontTheme: Json::optString($data, 'storefrontTheme'),
            storefrontTemplate: Json::optString($data, 'storefrontTemplate'),
            checkoutMode: Json::optString($data, 'checkoutMode'),
            externalCheckoutUrl: Json::optString($data, 'externalCheckoutUrl'),
            meta: $meta,
        );
    }
}
