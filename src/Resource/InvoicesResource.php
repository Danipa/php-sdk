<?php

declare(strict_types=1);

namespace Danipa\Sdk\Resource;

use Danipa\Sdk\Http\HttpClient;
use Danipa\Sdk\Model\CreateInvoiceRequest;
use Danipa\Sdk\Model\Invoice;
use Danipa\Sdk\RequestOptions;

/** Invoices resource — issue invoices that arrive as `SENT`. */
final class InvoicesResource
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    public function create(CreateInvoiceRequest $request, ?RequestOptions $options = null): Invoice
    {
        $body = $this->http->request(
            'POST',
            '/v1/merchants/me/invoices',
            $request->toArray(),
            [],
            $options,
        );
        return Invoice::fromArray($body);
    }
}
