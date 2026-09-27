<?php

declare(strict_types=1);

namespace Danipa\Sdk\Model;

use Danipa\Sdk\Internal\Json;

/**
 * The merchant's operating balance in one currency — the server's
 * `WalletAccountResponse` from `GET /v1/merchants/me/wallet/balances`. Business
 * money only (keyed on the merchant, never the owner's personal wallet), and
 * only the key's own environment. Money fields are decimal strings.
 */
final class WalletBalance
{
    public function __construct(
        public readonly ?string $id,
        public readonly string $currency,
        public readonly ?string $availableBalance,
        public readonly ?string $pendingBalance,
        public readonly ?string $status,
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
            id: Json::optString($data, 'id'),
            currency: Json::string($data, 'currency'),
            availableBalance: Json::optDecimal($data, 'availableBalance'),
            pendingBalance: Json::optDecimal($data, 'pendingBalance'),
            status: Json::optString($data, 'status'),
        );
    }
}
