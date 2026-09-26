<?php

declare(strict_types=1);

namespace Danipa\Sdk\Resource;

use Danipa\Sdk\Http\HttpClient;
use Danipa\Sdk\Model\CreatePaymentLinkRequest;
use Danipa\Sdk\Model\PaymentLink;
use Danipa\Sdk\RequestOptions;

/** Hosted payment links the merchant shares by URL. */
final class PaymentLinksResource
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * `POST /v1/merchants/me/payment-links`.
     *
     * NOTE: this route is not yet on the API-key surface — today an API key
     * gets `403 API_KEY_SCOPE_DENIED` ({@see \Danipa\Sdk\Errors\PermissionError}).
     * It becomes callable once the backend work in #5573 lands.
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
}
