<?php

declare(strict_types=1);

namespace Danipa\Sdk\Tests\Resource;

use Danipa\Sdk\Errors\PermissionError;
use Danipa\Sdk\Model\CreatePaymentLinkRequest;
use Danipa\Sdk\Model\PaymentLinkStatus;
use Danipa\Sdk\RequestOptions;
use Danipa\Sdk\Resource\PaymentLinksResource;
use Danipa\Sdk\Tests\Support\FakeClient;
use Danipa\Sdk\Tests\Support\Sdk;
use PHPUnit\Framework\TestCase;

final class PaymentLinksResourceTest extends TestCase
{
    private const RESPONSE = <<<'JSON'
        {"id":"pl_1","customerId":null,"shortCode":"abc123","url":"https://pay.danipa.com/p/abc123","title":"Coffee",
         "description":"Flat white","amount":125.00,"currency":"GHS","isAmountFixed":true,"status":"ACTIVE",
         "totalCollected":0,"paymentCount":0,"createdAt":"2026-05-01T12:00:00Z","merchantName":"Kin & Calabash",
         "merchantBrandColor":"#1f6b4a"}
        JSON;

    public function testCreatePostsTheServerDtoShape(): void
    {
        $fake = (new FakeClient())->reply(201, self::RESPONSE);
        (new PaymentLinksResource(Sdk::http($fake)))->create(new CreatePaymentLinkRequest(
            title: 'Coffee',
            description: 'Flat white',
            amount: '125.00',
            redirectUrl: 'https://shop.example/thanks',
        ));

        $req = $fake->lastRequest();
        self::assertSame('POST', $req->getMethod());
        self::assertSame(Sdk::BASE . '/v1/merchants/me/payment-links', (string) $req->getUri());
        self::assertSame(
            [
                'title' => 'Coffee',
                'description' => 'Flat white',
                'amount' => '125.00',
                'redirectUrl' => 'https://shop.example/thanks',
            ],
            $fake->lastJsonBody(),
            'optional nulls (currency, customerId, ...) are omitted',
        );
        self::assertNotSame('', $req->getHeaderLine('Idempotency-Key'));
    }

    public function testOpenAmountLinkSendsRange(): void
    {
        $fake = (new FakeClient())->reply(201, self::RESPONSE);
        (new PaymentLinksResource(Sdk::http($fake)))->create(new CreatePaymentLinkRequest(
            title: 'Donation',
            currency: 'CAD',
            isAmountFixed: false,
            minAmount: 5,
            maxAmount: '500.00',
            customerId: 'c-1',
        ));

        self::assertSame(
            [
                'title' => 'Donation',
                'customerId' => 'c-1',
                'currency' => 'CAD',
                'isAmountFixed' => false,
                'minAmount' => '5',
                'maxAmount' => '500.00',
            ],
            $fake->lastJsonBody(),
        );
    }

    public function testResponseMoneyIsParsedFromAJsonNumber(): void
    {
        $fake = (new FakeClient())->reply(201, self::RESPONSE, ['X-Idempotent-Replay' => 'true']);
        $link = (new PaymentLinksResource(Sdk::http($fake)))->create(
            new CreatePaymentLinkRequest(title: 'Coffee'),
            RequestOptions::withIdempotencyKey('order-1'),
        );

        self::assertSame('pl_1', $link->id);
        self::assertSame('125.00', $link->amount);
        self::assertSame('0', $link->totalCollected);
        self::assertSame(PaymentLinkStatus::ACTIVE, $link->status);
        self::assertSame('abc123', $link->shortCode);
        self::assertSame('#1f6b4a', $link->merchantBrandColor);
        self::assertNull($link->customerId);
        self::assertNotNull($link->meta);
        self::assertSame('order-1', $link->meta->idempotencyKey);
        self::assertTrue($link->meta->idempotentReplay);
    }

    public function testUnknownStatusDoesNotThrow(): void
    {
        $fake = (new FakeClient())->reply(201, '{"id":"pl_2","status":"ARCHIVED"}');
        $link = (new PaymentLinksResource(Sdk::http($fake)))->create(new CreatePaymentLinkRequest(title: 'x'));

        self::assertSame(PaymentLinkStatus::UNKNOWN, $link->status);
        self::assertSame('ARCHIVED', $link->statusRaw);
    }

    public function testTitleIsRequired(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CreatePaymentLinkRequest(title: ' ');
    }

    public function testApiKeyScopeDeniedTodayIsAPermissionError(): void
    {
        $fake = (new FakeClient())->reply(403, '{"code":"API_KEY_SCOPE_DENIED","description":"API key access is not permitted for this request"}');
        try {
            (new PaymentLinksResource(Sdk::http($fake)))->create(new CreatePaymentLinkRequest(title: 'x'));
            self::fail('expected PermissionError');
        } catch (PermissionError $e) {
            self::assertSame('API_KEY_SCOPE_DENIED', $e->errorCode);
        }
    }
}
