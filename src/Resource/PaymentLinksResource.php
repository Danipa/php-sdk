<?php

declare(strict_types=1);

namespace Danipa\Sdk\Resource;

use Danipa\Sdk\Http\HttpClient;
use Danipa\Sdk\Model\CreatePaymentLinkRequest;
use Danipa\Sdk\Model\PaymentLink;
use Danipa\Sdk\RequestOptions;

/** Payment links resource — hosted payment links the merchant shares by URL. */
final class PaymentLinksResource
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    public function create(CreatePaymentLinkRequest $request, ?RequestOptions $options = null): PaymentLink
    {
        $body = $this->http->request(
            'POST',
            '/v1/merchants/me/payment-links',
            $request->toArray(),
            [],
            $options,
        );
        return PaymentLink::fromArray($body);
    }
}
