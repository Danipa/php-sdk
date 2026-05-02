<?php

declare(strict_types=1);

namespace Danipa\Sdk\Resource;

use Danipa\Sdk\Http\HttpClient;
use Danipa\Sdk\Model\WalletBalance;

/**
 * Wallets resource. Mirrors Node SDK's `WalletsResource` 1:1.
 *
 * ```php
 * $balance = $danipa->wallets()->getBalance();         // default-currency wallet
 * $ghs     = $danipa->wallets()->getBalance('GHS');    // explicit currency
 * ```
 */
final class WalletsResource
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    public function getBalance(?string $currency = null): WalletBalance
    {
        $query = $currency !== null ? ['currency' => $currency] : [];
        $body = $this->http->request('GET', '/v1/wallets/balance', null, $query);
        return WalletBalance::fromArray($body);
    }
}
