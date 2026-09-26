<?php

declare(strict_types=1);

namespace Danipa\Sdk\Model;

use Danipa\Sdk\Internal\Json;
use Danipa\Sdk\ResponseMeta;

/**
 * An invoice — the server's full `InvoiceResponse`. Money fields are decimal
 * strings. `$status` is {@see InvoiceStatus::UNKNOWN} for a value this SDK
 * version does not know; `$statusRaw` keeps what the server sent.
 */
final class Invoice
{
    /**
     * @param list<InvoiceLineItem> $lineItems
     * @param list<InvoiceTaxLine>  $taxBreakdown Per-tax legs (GST/HST/QST); empty for single-rate invoices.
     */
    public function __construct(
        public readonly string $id,
        public readonly ?string $invoiceNumber,
        public readonly ?string $customerId,
        public readonly ?string $customerName,
        public readonly ?string $customerEmail,
        public readonly ?string $customerPhone,
        public readonly array $lineItems,
        public readonly ?string $subtotal,
        public readonly ?string $taxAmount,
        public readonly array $taxBreakdown,
        public readonly ?string $total,
        public readonly ?string $currency,
        public readonly InvoiceStatus $status,
        public readonly ?string $statusRaw,
        public readonly ?string $dueDate,
        public readonly ?string $paidAt,
        public readonly ?string $sentAt,
        public readonly ?string $voidedAt,
        public readonly ?string $paymentMethod,
        public readonly ?string $paymentNote,
        public readonly ?string $paymentLinkUrl,
        public readonly ?string $notes,
        public readonly ?string $createdAt,
        public readonly ?ResponseMeta $meta = null,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     * @throws \Danipa\Sdk\Internal\MalformedResponse
     * @internal
     */
    public static function fromArray(array $data, ?ResponseMeta $meta = null): self
    {
        $status = Json::optString($data, 'status');
        return new self(
            id: Json::string($data, 'id'),
            invoiceNumber: Json::optString($data, 'invoiceNumber'),
            customerId: Json::optString($data, 'customerId'),
            customerName: Json::optString($data, 'customerName'),
            customerEmail: Json::optString($data, 'customerEmail'),
            customerPhone: Json::optString($data, 'customerPhone'),
            lineItems: array_map(InvoiceLineItem::fromArray(...), Json::objectList($data, 'lineItems')),
            subtotal: Json::optDecimal($data, 'subtotal'),
            taxAmount: Json::optDecimal($data, 'taxAmount'),
            taxBreakdown: array_map(InvoiceTaxLine::fromArray(...), Json::objectList($data, 'taxBreakdown')),
            total: Json::optDecimal($data, 'total'),
            currency: Json::optString($data, 'currency'),
            status: InvoiceStatus::parse($status),
            statusRaw: $status,
            dueDate: Json::optString($data, 'dueDate'),
            paidAt: Json::optString($data, 'paidAt'),
            sentAt: Json::optString($data, 'sentAt'),
            voidedAt: Json::optString($data, 'voidedAt'),
            paymentMethod: Json::optString($data, 'paymentMethod'),
            paymentNote: Json::optString($data, 'paymentNote'),
            paymentLinkUrl: Json::optString($data, 'paymentLinkUrl'),
            notes: Json::optString($data, 'notes'),
            createdAt: Json::optString($data, 'createdAt'),
            meta: $meta,
        );
    }
}
