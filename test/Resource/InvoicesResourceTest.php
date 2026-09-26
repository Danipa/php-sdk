<?php

declare(strict_types=1);

namespace Danipa\Sdk\Tests\Resource;

use Danipa\Sdk\Errors\ResponseParseError;
use Danipa\Sdk\Model\CreateInvoiceRequest;
use Danipa\Sdk\Model\InvoiceLineItemRequest;
use Danipa\Sdk\Model\InvoiceStatus;
use Danipa\Sdk\Resource\InvoicesResource;
use Danipa\Sdk\Tests\Support\FakeClient;
use Danipa\Sdk\Tests\Support\Sdk;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InvoicesResourceTest extends TestCase
{
    private static function response(string $status): string
    {
        return <<<JSON
            {"id":"inv_1","invoiceNumber":"INV-0001","customerName":"Ama K.","customerEmail":"a@b.com",
             "lineItems":[{"description":"Service","quantity":2,"unitPrice":250.00,"lineTotal":500.00,"taxRatePct":13}],
             "subtotal":500.00,"taxAmount":65.00,
             "taxBreakdown":[{"label":"HST 13%","ratePct":13.00,"amount":65.00}],
             "total":565.00,"currency":"CAD","status":"$status","dueDate":"2026-06-01",
             "voidedAt":"2026-05-03T09:00:00Z","createdAt":"2026-05-01T12:00:00Z"}
            JSON;
    }

    public function testCreatePostsTheServerDtoShape(): void
    {
        $fake = (new FakeClient())->reply(201, self::response('SENT'));
        (new InvoicesResource(Sdk::http($fake)))->create(new CreateInvoiceRequest(
            customerName: 'Ama K.',
            lineItems: [
                new InvoiceLineItemRequest('Service', 2, '250.00', taxRatePct: '13'),
                new InvoiceLineItemRequest('Travel', 1, 40),
            ],
            customerPhone: '+14165550100',
            taxAmount: '65.00',
            dueDate: '2026-06-01',
        ));

        $req = $fake->lastRequest();
        self::assertSame(Sdk::BASE . '/v1/merchants/me/invoices', (string) $req->getUri());
        self::assertNotSame('', $req->getHeaderLine('Idempotency-Key'));
        self::assertSame(
            [
                'customerName' => 'Ama K.',
                'customerPhone' => '+14165550100',
                'lineItems' => [
                    ['description' => 'Service', 'quantity' => 2, 'unitPrice' => '250.00', 'taxRatePct' => '13'],
                    ['description' => 'Travel', 'quantity' => 1, 'unitPrice' => '40'],
                ],
                'taxAmount' => '65.00',
                'dueDate' => '2026-06-01',
            ],
            $fake->lastJsonBody(),
            'currency omitted → the merchant\'s billing currency',
        );
    }

    public function testFullInvoiceResponseIsParsedWithExactMoney(): void
    {
        $fake = (new FakeClient())->reply(201, self::response('SENT'));
        $inv = (new InvoicesResource(Sdk::http($fake)))->create(new CreateInvoiceRequest(
            customerName: 'Ama K.',
            lineItems: [new InvoiceLineItemRequest('Service', 2, '250.00')],
        ));

        self::assertSame('inv_1', $inv->id);
        self::assertSame('INV-0001', $inv->invoiceNumber);
        self::assertSame(InvoiceStatus::SENT, $inv->status);
        self::assertSame('500.00', $inv->subtotal);
        self::assertSame('65.00', $inv->taxAmount);
        self::assertSame('565.00', $inv->total);
        self::assertCount(1, $inv->lineItems);
        self::assertSame('250.00', $inv->lineItems[0]->unitPrice);
        self::assertSame('500.00', $inv->lineItems[0]->lineTotal);
        self::assertSame('13', $inv->lineItems[0]->taxRatePct);
        self::assertCount(1, $inv->taxBreakdown);
        self::assertSame('HST 13%', $inv->taxBreakdown[0]->label);
        self::assertSame('65.00', $inv->taxBreakdown[0]->amount);
    }

    public function testVoidedStatusParses(): void
    {
        $fake = (new FakeClient())->reply(201, self::response('VOIDED'));
        $inv = (new InvoicesResource(Sdk::http($fake)))->create(new CreateInvoiceRequest(
            customerName: 'x',
            lineItems: [new InvoiceLineItemRequest('a', 1, '1.00')],
        ));

        self::assertSame(InvoiceStatus::VOIDED, $inv->status);
        self::assertSame('2026-05-03T09:00:00Z', $inv->voidedAt);
    }

    /** @return iterable<string, array{string}> */
    public static function unknownStatuses(): iterable
    {
        yield 'future value' => ['DISPUTED'];
        yield 'retired value' => ['CANCELLED'];
    }

    #[DataProvider('unknownStatuses')]
    public function testUnknownStatusParsesAsUnknown(string $status): void
    {
        $fake = (new FakeClient())->reply(201, self::response($status));
        $inv = (new InvoicesResource(Sdk::http($fake)))->create(new CreateInvoiceRequest(
            customerName: 'x',
            lineItems: [new InvoiceLineItemRequest('a', 1, '1.00')],
        ));

        self::assertSame(InvoiceStatus::UNKNOWN, $inv->status);
        self::assertSame($status, $inv->statusRaw);
    }

    /** @return iterable<string, array{string}> */
    public static function malformedLines(): iterable
    {
        yield 'missing description' => ['{"quantity":1,"unitPrice":1.00}'];
        yield 'missing quantity' => ['{"description":"a","unitPrice":1.00}'];
        yield 'missing unitPrice' => ['{"description":"a","quantity":1}'];
        yield 'quantity as string' => ['{"description":"a","quantity":"1","unitPrice":1.00}'];
        yield 'unitPrice not numeric' => ['{"description":"a","quantity":1,"unitPrice":"abc"}'];
    }

    #[DataProvider('malformedLines')]
    public function testMalformedLineItemIsAParseErrorNotADefault(string $line): void
    {
        $fake = (new FakeClient())->reply(201, '{"id":"inv_1","status":"SENT","lineItems":[' . $line . ']}');
        $this->expectException(ResponseParseError::class);
        (new InvoicesResource(Sdk::http($fake)))->create(new CreateInvoiceRequest(
            customerName: 'x',
            lineItems: [new InvoiceLineItemRequest('a', 1, '1.00')],
        ));
    }

    public function testLineWithoutOptionalFieldsParses(): void
    {
        $fake = (new FakeClient())->reply(
            201,
            '{"id":"inv_1","status":"SENT","lineItems":[{"description":"a","quantity":3,"unitPrice":2.50}]}',
        );
        $inv = (new InvoicesResource(Sdk::http($fake)))->create(new CreateInvoiceRequest(
            customerName: 'x',
            lineItems: [new InvoiceLineItemRequest('a', 3, '2.50')],
        ));

        self::assertSame(3, $inv->lineItems[0]->quantity);
        self::assertSame('2.50', $inv->lineItems[0]->unitPrice);
        self::assertNull($inv->lineItems[0]->lineTotal);
        self::assertNull($inv->lineItems[0]->taxRatePct);
    }

    public function testCustomerNameAndLinesAreRequired(): void
    {
        try {
            new CreateInvoiceRequest(customerName: '', lineItems: [new InvoiceLineItemRequest('a', 1, '1')]);
            self::fail('blank customerName must be rejected');
        } catch (\InvalidArgumentException) {
        }
        $this->expectException(\InvalidArgumentException::class);
        new CreateInvoiceRequest(customerName: 'x', lineItems: []);
    }
}
