<?php

declare(strict_types=1);

namespace Danipa\Sdk\Resource;

use Danipa\Sdk\Http\HttpClient;
use Danipa\Sdk\Model\Collection;
use Danipa\Sdk\Model\CreateCollectionRequest;
use Danipa\Sdk\RequestOptions;

/**
 * Collections resource — pull-from-payer flows (request-to-pay over MoMo,
 * card debit, etc.). Mirrors Node SDK's `CollectionsResource` 1:1.
 *
 * Get an instance via {@see \Danipa\Sdk\Danipa::collections()} — never
 * construct directly.
 */
final class CollectionsResource
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * Initiate a pull-from-payer collection. Returns a Collection in
     * `PENDING` state — listen for `collection.completed` /
     * `collection.failed` webhooks for the terminal state, or poll
     * {@see self::get()}.
     */
    public function create(CreateCollectionRequest $request, ?RequestOptions $options = null): Collection
    {
        $body = $this->http->request(
            'POST',
            '/v1/collections',
            $request->toArray(),
            [],
            $options,
        );
        return Collection::fromArray($body);
    }

    public function get(string $id): Collection
    {
        $body = $this->http->request(
            'GET',
            '/v1/collections/' . rawurlencode($id),
        );
        return Collection::fromArray($body);
    }
}
