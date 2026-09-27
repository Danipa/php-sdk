<?php

declare(strict_types=1);

namespace Danipa\Sdk\Resource;

use Danipa\Sdk\Http\HttpClient;
use Danipa\Sdk\Model\Merchant;

/** The authenticated merchant. */
final class MerchantsResource
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * `GET /v1/merchants/me` — the profile of the merchant that owns the API
     * key. Needs the key's `account:read` scope; use it to check a key works.
     *
     * @throws \Danipa\Sdk\Errors\DanipaError
     */
    public function me(): Merchant
    {
        return $this->http->request('GET', '/v1/merchants/me')->parse(Merchant::fromArray(...));
    }
}
