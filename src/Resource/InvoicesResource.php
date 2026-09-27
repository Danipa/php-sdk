<?php

declare(strict_types=1);

namespace Danipa\Sdk\Resource;

use Danipa\Sdk\Http\HttpClient;
use Danipa\Sdk\Internal\Ids;
use Danipa\Sdk\Model\CreateInvoiceRequest;
use Danipa\Sdk\Model\Invoice;
use Danipa\Sdk\Model\InvoiceStatus;
use Danipa\Sdk\Model\Page;
use Danipa\Sdk\RequestOptions;
use Danipa\Sdk\ResponseMeta;

/**
 * Merchant invoices. An API key only sees and changes invoices of its own
 * environment (another environment's invoice is a 404). Updating a draft,
 * the PDF, stats, offline payments and bulk import are dashboard-only and
 * not on the API-key surface.
 */
final class InvoicesResource
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * `POST /v1/merchants/me/invoices` (scope `payments:write`). The invoice
     * is created as a `DRAFT`; {@see send()} it to issue it.
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

    /**
     * `GET /v1/merchants/me/invoices` (scope `payments:read`), newest first.
     *
     * @param InvoiceStatus|string|null $status Only invoices in this status: an {@see InvoiceStatus} or its name
     *                                          (DRAFT, SENT, PAID, OVERDUE, VOIDED; case-insensitive). Anything
     *                                          else is rejected before sending.
     * @param string|null               $q      Case-insensitive search: invoice-number prefix or
     *                                          customer-name substring.
     * @param int                       $page   Zero-based.
     * @param int                       $size   1–200 (server default 20).
     * @return Page<Invoice>
     * @throws \Danipa\Sdk\Errors\DanipaError
     * @throws \InvalidArgumentException on a non-UUID id or out-of-range paging, before anything is sent.
     */
    public function list(
        InvoiceStatus|string|null $status = null,
        ?string $q = null,
        int $page = 0,
        int $size = 20,
    ): Page {
        $query = [
            'status' => self::statusFilter($status),
            'q' => $q,
        ] + Ids::paging($page, $size);
        return $this->http
            ->request('GET', '/v1/merchants/me/invoices', null, $query)
            ->parse(static fn (array $data, ResponseMeta $meta): Page
                => Page::fromArray($data, Invoice::fromArray(...), $meta));
    }

    /**
     * `GET /v1/merchants/me/invoices/{id}` (scope `payments:read`).
     *
     * @throws \Danipa\Sdk\Errors\DanipaError
     * @throws \InvalidArgumentException on a non-UUID id or out-of-range paging, before anything is sent.
     */
    public function get(string $id): Invoice
    {
        return $this->http
            ->request('GET', '/v1/merchants/me/invoices/' . Ids::uuid($id, 'invoice id'))
            ->parse(Invoice::fromArray(...));
    }

    /**
     * `POST /v1/merchants/me/invoices/{id}/send` (scope `payments:write`).
     * Moves a `DRAFT` invoice to `SENT` and creates its payment link
     * (`$invoice->paymentLinkUrl`). A non-draft invoice is a 422.
     *
     * @throws \Danipa\Sdk\Errors\DanipaError
     * @throws \InvalidArgumentException on a non-UUID id or out-of-range paging, before anything is sent.
     */
    public function send(string $id, ?RequestOptions $options = null): Invoice
    {
        return $this->http
            ->request('POST', '/v1/merchants/me/invoices/' . Ids::uuid($id, 'invoice id') . '/send', null, [], $options)
            ->parse(Invoice::fromArray(...));
    }

    /**
     * `POST /v1/merchants/me/invoices/{id}/void` (scope `payments:write`).
     * Voids an unpaid invoice and deactivates its payment link. A paid
     * invoice is a 409 ({@see \Danipa\Sdk\Errors\ConflictError}).
     *
     * @throws \Danipa\Sdk\Errors\DanipaError
     * @throws \InvalidArgumentException on a non-UUID id or out-of-range paging, before anything is sent.
     */
    public function void(string $id, ?RequestOptions $options = null): Invoice
    {
        return $this->http
            ->request('POST', '/v1/merchants/me/invoices/' . Ids::uuid($id, 'invoice id') . '/void', null, [], $options)
            ->parse(Invoice::fromArray(...));
    }

    /** The server turns an unknown status into a 500, so only its enum values are sent. */
    private static function statusFilter(InvoiceStatus|string|null $status): ?string
    {
        if ($status === null) {
            return null;
        }
        $parsed = $status instanceof InvoiceStatus ? $status : InvoiceStatus::tryFrom(strtoupper(trim($status)));
        if ($parsed === null || $parsed === InvoiceStatus::UNKNOWN) {
            throw new \InvalidArgumentException(
                'Danipa SDK: invoice status filter must be one of DRAFT, SENT, PAID, OVERDUE, VOIDED',
            );
        }
        return $parsed->value;
    }
}
