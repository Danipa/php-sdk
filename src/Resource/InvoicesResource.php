<?php

declare(strict_types=1);

namespace Danipa\Sdk\Resource;

use Danipa\Sdk\Http\HttpClient;
use Danipa\Sdk\Model\CreateInvoiceRequest;
use Danipa\Sdk\Model\Invoice;
use Danipa\Sdk\RequestOptions;

/** Merchant invoices. */
final class InvoicesResource
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * `POST /v1/merchants/me/invoices`.
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
    public function create(CreateInvoiceRequest $request, ?RequestOptions $options = null): Invoice
    {
        return $this->http
            ->request('POST', '/v1/merchants/me/invoices', $request->toArray(), [], $options)
            ->parse(Invoice::fromArray(...));
    }
}
