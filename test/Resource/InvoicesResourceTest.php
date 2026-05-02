<?php

declare(strict_types=1);

namespace Danipa\Sdk\Tests\Resource;

use Danipa\Sdk\Http\HttpClient;
use Danipa\Sdk\Model\CreateInvoiceRequest;
use Danipa\Sdk\Model\InvoiceLineItem;
use Danipa\Sdk\Model\InvoiceStatus;
use Danipa\Sdk\Resource\InvoicesResource;
use Danipa\Sdk\Tests\Support\FakeClient;
use PHPUnit\Framework\TestCase;

final class InvoicesResourceTest extends TestCase
{
    private function http(FakeClient $fake): HttpClient
    {
        return new HttpClient(
            apiKey: 'dk_test_x',
            baseUrl: 'https://api.sandbox.danipa.com/ms',
            client: $fake,
            sleeper: static fn (int $ms) => null,
        );
    }

    public function testCreatePostsToInvoices(): void
    {
        $fake = (new FakeClient())->reply(200, '{"id":"inv_1","merchantId":"m_1","amount":"500.00","currency":"GHS","dueDate":"2026-06-01","status":"SENT","customerEmail":"a@b.com","lineItems":[{"description":"Service","quantity":2,"unitPrice":"250.00"}],"createdAt":"2026-05-01T12:00:00Z"}');
        $resource = new InvoicesResource($this->http($fake));

        $inv = $resource->create(new CreateInvoiceRequest(
            customerEmail: 'a@b.com',
            currency: 'GHS',
            dueDate: '2026-06-01',
            lineItems: [new InvoiceLineItem(description: 'Service', quantity: 2, unitPrice: '250.00')],
        ));

        self::assertSame('inv_1', $inv->id);
        self::assertSame(InvoiceStatus::SENT, $inv->status);
        self::assertCount(1, $inv->lineItems);
        self::assertSame('Service', $inv->lineItems[0]->description);
        self::assertStringEndsWith('/v1/merchants/me/invoices', (string) $fake->lastRequest()->getUri());
    }
}
