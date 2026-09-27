<?php

declare(strict_types=1);

namespace Danipa\Sdk\Resource;

use Danipa\Sdk\Http\HttpClient;
use Danipa\Sdk\Internal\Ids;
use Danipa\Sdk\Model\CreatePaymentLinkRequest;
use Danipa\Sdk\Model\Page;
use Danipa\Sdk\Model\PaymentLink;
use Danipa\Sdk\Model\PaymentLinkDeleteResult;
use Danipa\Sdk\Model\UpdatePaymentLinkRequest;
use Danipa\Sdk\RequestOptions;
use Danipa\Sdk\ResponseMeta;

/**
 * Hosted payment links the merchant shares by URL. An API key only sees and
 * changes links of its own environment. There is no "get one link" route on
 * the API-key surface; use {@see list()}.
 */
final class PaymentLinksResource
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * `POST /v1/merchants/me/payment-links` (scope `payments:write`).
     *
     * Always sent with an `Idempotency-Key` (auto-generated unless given in
     * `$options`), so retries are safe.
     *
     * @throws \Danipa\Sdk\Errors\DanipaError
     */
    public function create(CreatePaymentLinkRequest $request, ?RequestOptions $options = null): PaymentLink
    {
        return $this->http
            ->request('POST', '/v1/merchants/me/payment-links', $request->toArray(), [], $options)
            ->parse(PaymentLink::fromArray(...));
    }

    /**
     * `GET /v1/merchants/me/payment-links` (scope `payments:read`). Zero-based
     * `$page`; `$size` 1–200 (server default 20).
     *
     * @return Page<PaymentLink>
     * @throws \Danipa\Sdk\Errors\DanipaError
     * @throws \InvalidArgumentException on a non-UUID id or out-of-range paging, before anything is sent.
     */
    public function list(int $page = 0, int $size = 20): Page
    {
        return $this->http
            ->request('GET', '/v1/merchants/me/payment-links', null, Ids::paging($page, $size))
            ->parse(static fn (array $data, ResponseMeta $meta): Page
                => Page::fromArray($data, PaymentLink::fromArray(...), $meta));
    }

    /**
     * `PUT /v1/merchants/me/payment-links/{id}` (scope `payments:write`). A
     * partial update of an `ACTIVE` link; null fields are left unchanged.
     *
     * @throws \Danipa\Sdk\Errors\DanipaError
     * @throws \InvalidArgumentException on a non-UUID id or out-of-range paging, before anything is sent.
     */
    public function update(string $id, UpdatePaymentLinkRequest $request, ?RequestOptions $options = null): PaymentLink
    {
        return $this->http
            ->request(
                'PUT',
                '/v1/merchants/me/payment-links/' . Ids::uuid($id, 'payment link id'),
                $request->toArray(),
                [],
                $options,
            )
            ->parse(PaymentLink::fromArray(...));
    }

    /**
     * `DELETE /v1/merchants/me/payment-links/{id}` (scope `payments:write`).
     * Deletes a link that never took a payment; one with payments is
     * deactivated instead. The result says which happened.
     *
     * @throws \Danipa\Sdk\Errors\DanipaError
     * @throws \InvalidArgumentException on a non-UUID id or out-of-range paging, before anything is sent.
     */
    public function delete(string $id, ?RequestOptions $options = null): PaymentLinkDeleteResult
    {
        $response = $this->http->request(
            'DELETE',
            '/v1/merchants/me/payment-links/' . Ids::uuid($id, 'payment link id'),
            null,
            [],
            $options,
        );
        if ($response->meta->status === 204 || trim($response->body) === '') {
            return PaymentLinkDeleteResult::deleted($response->meta);
        }
        return $response->parse(PaymentLinkDeleteResult::fromArray(...));
    }
}
