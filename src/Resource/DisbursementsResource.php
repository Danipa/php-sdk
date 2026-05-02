<?php

declare(strict_types=1);

namespace Danipa\Sdk\Resource;

use Danipa\Sdk\Http\HttpClient;
use Danipa\Sdk\Model\CreateDisbursementRequest;
use Danipa\Sdk\Model\Disbursement;
use Danipa\Sdk\RequestOptions;

/**
 * Disbursements resource — push-to-payee flows (MoMo wallet, bank account).
 * Mirrors Node SDK's `DisbursementsResource` 1:1. Strongly recommended to
 * pass an idempotency key on `create()` — disbursement creation is the most
 * expensive operation to accidentally duplicate.
 */
final class DisbursementsResource
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    public function create(CreateDisbursementRequest $request, ?RequestOptions $options = null): Disbursement
    {
        $body = $this->http->request(
            'POST',
            '/v1/disbursements',
            $request->toArray(),
            [],
            $options,
        );
        return Disbursement::fromArray($body);
    }

    public function get(string $id): Disbursement
    {
        $body = $this->http->request(
            'GET',
            '/v1/disbursements/' . rawurlencode($id),
        );
        return Disbursement::fromArray($body);
    }
}
