<?php

declare(strict_types=1);

namespace Danipa\Sdk\Resource;

use Danipa\Sdk\Http\HttpClient;
use Danipa\Sdk\Model\WalletBalance;

/** The merchant's operating balances. */
final class BalancesResource
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * `GET /v1/merchants/me/wallet/balances` (scope `payments:read`): one
     * entry per currency held, for the key's environment only. Empty when
     * nothing has settled yet.
     *
     * @return list<WalletBalance>
     * @throws \Danipa\Sdk\Errors\DanipaError
     */
    public function list(): array
    {
        return $this->http
            ->request('GET', '/v1/merchants/me/wallet/balances')
            ->parseList(WalletBalance::fromArray(...));
    }
}
