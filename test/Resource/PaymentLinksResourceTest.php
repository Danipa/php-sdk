<?php

declare(strict_types=1);

namespace Danipa\Sdk\Tests\Resource;

use Danipa\Sdk\Errors\NotFoundError;
use Danipa\Sdk\Errors\PermissionError;
use Danipa\Sdk\Errors\ValidationError;
use Danipa\Sdk\Model\UpdatePaymentLinkRequest;
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

    public function testScopeDeniedIsAPermissionError(): void
    {
        $fake = (new FakeClient())->reply(403, '{"code":"API_KEY_SCOPE_DENIED","description":"API key access is not permitted for this request"}');
        try {
            (new PaymentLinksResource(Sdk::http($fake)))->create(new CreatePaymentLinkRequest(title: 'x'));
            self::fail('expected PermissionError');
        } catch (PermissionError $e) {
            self::assertSame('API_KEY_SCOPE_DENIED', $e->errorCode);
        }
    }

    private const ID = '3f2b8c1e-9a4d-4e2f-8b6a-1c2d3e4f5a6b';

    public function testListGetsAPageWithPagingParams(): void
    {
        $fake = (new FakeClient())->reply(
            200,
            '{"content":[' . self::RESPONSE . '],"totalElements":41,"totalPages":3,"number":1,"size":20}',
        );
        $page = (new PaymentLinksResource(Sdk::http($fake)))->list(page: 1, size: 20);

        $req = $fake->lastRequest();
        self::assertSame('GET', $req->getMethod());
        self::assertSame(Sdk::BASE . '/v1/merchants/me/payment-links?page=1&size=20', (string) $req->getUri());
        self::assertSame('dk_test_secret', $req->getHeaderLine('X-API-Key'));
        self::assertSame('', $req->getHeaderLine('Idempotency-Key'), 'reads carry no idempotency key');
        self::assertSame('', $req->getHeaderLine('Authorization'));
        self::assertCount(1, $page->items);
        self::assertSame('125.00', $page->items[0]->amount);
        self::assertSame(41, $page->totalElements);
        self::assertSame(3, $page->totalPages);
        self::assertSame(1, $page->number);
        self::assertTrue($page->hasNext());
        self::assertNotNull($page->meta);
        self::assertSame(200, $page->meta->status);
    }

    public function testListReadsTheNestedPageMetadataShapeToo(): void
    {
        $fake = (new FakeClient())->reply(
            200,
            '{"content":[],"page":{"size":5,"number":0,"totalElements":0,"totalPages":0}}',
        );
        $page = (new PaymentLinksResource(Sdk::http($fake)))->list(size: 5);

        self::assertSame([], $page->items);
        self::assertSame(5, $page->size);
        self::assertSame(0, $page->totalElements);
        self::assertFalse($page->hasNext());
        self::assertSame(
            Sdk::BASE . '/v1/merchants/me/payment-links?page=0&size=5',
            (string) $fake->lastRequest()->getUri(),
        );
    }

    public function testListRejectsOutOfRangePagingBeforeSending(): void
    {
        $fake = new FakeClient();
        $links = new PaymentLinksResource(Sdk::http($fake));
        foreach ([[-1, 20], [0, 0], [0, 201]] as [$page, $size]) {
            try {
                $links->list($page, $size);
                self::fail("page=$page size=$size must be rejected");
            } catch (\InvalidArgumentException) {
            }
        }
        self::assertSame([], $fake->sent());
    }

    public function testUpdatePutsOnlyTheChangedFields(): void
    {
        $fake = (new FakeClient())->reply(200, self::RESPONSE);
        $link = (new PaymentLinksResource(Sdk::http($fake)))->update(self::ID, new UpdatePaymentLinkRequest(
            title: 'Cortado',
            amount: '9.50',
            expiresAt: '2026-12-31T23:59:59Z',
        ), RequestOptions::withIdempotencyKey('upd-1'));

        $req = $fake->lastRequest();
        self::assertSame('PUT', $req->getMethod());
        self::assertSame(Sdk::BASE . '/v1/merchants/me/payment-links/' . self::ID, (string) $req->getUri());
        self::assertSame('upd-1', $req->getHeaderLine('Idempotency-Key'));
        self::assertSame('application/json', $req->getHeaderLine('Content-Type'));
        self::assertSame(
            ['title' => 'Cortado', 'amount' => '9.50', 'expiresAt' => '2026-12-31T23:59:59Z'],
            $fake->lastJsonBody(),
        );
        self::assertSame('pl_1', $link->id);
    }

    public function testUpdateRejectsANonUuidIdBeforeSending(): void
    {
        $fake = new FakeClient();
        $this->expectException(\InvalidArgumentException::class);
        (new PaymentLinksResource(Sdk::http($fake)))->update('abc123', new UpdatePaymentLinkRequest(title: 'x'));
    }

    public function testUpdateOfAMissingLinkIsNotFound(): void
    {
        $fake = (new FakeClient())->reply(404, '{"code":"PAYMENT_LINK_NOT_FOUND","description":"not found"}');
        $this->expectException(NotFoundError::class);
        (new PaymentLinksResource(Sdk::http($fake)))->update(self::ID, new UpdatePaymentLinkRequest(title: 'x'));
    }

    public function testDeleteWith204MeansDeleted(): void
    {
        $fake = (new FakeClient())->reply(204);
        $result = (new PaymentLinksResource(Sdk::http($fake)))->delete(self::ID);

        $req = $fake->lastRequest();
        self::assertSame('DELETE', $req->getMethod());
        self::assertSame(Sdk::BASE . '/v1/merchants/me/payment-links/' . self::ID, (string) $req->getUri());
        self::assertNotSame('', $req->getHeaderLine('Idempotency-Key'));
        self::assertSame('', (string) $req->getBody());
        self::assertTrue($result->deleted);
        self::assertFalse($result->deactivated);
        self::assertNotNull($result->meta);
        self::assertSame(204, $result->meta->status);
    }

    public function testDeleteOfALinkWithPaymentsReportsDeactivated(): void
    {
        $fake = (new FakeClient())->reply(
            200,
            '{"action":"deactivated",'
            . '"message":"Payment link has transactions and was deactivated instead of deleted."}',
        );
        $result = (new PaymentLinksResource(Sdk::http($fake)))->delete(self::ID);

        self::assertFalse($result->deleted);
        self::assertTrue($result->deactivated);
        self::assertSame('Payment link has transactions and was deactivated instead of deleted.', $result->message);
    }

    public function testStepUpRequiredSurfacesItsCode(): void
    {
        $fake = (new FakeClient())->reply(403, '{"code":"STEP_UP_REQUIRED","description":"step-up required"}');
        try {
            (new PaymentLinksResource(Sdk::http($fake)))->delete(self::ID);
            self::fail('expected PermissionError');
        } catch (PermissionError $e) {
            self::assertSame('STEP_UP_REQUIRED', $e->errorCode);
            self::assertSame(403, $e->status);
        }
    }

    public function testCreateValidationErrorCarriesFieldErrors(): void
    {
        $fake = (new FakeClient())->reply(
            400,
            '{"errors":[{"code":"VALIDATION_FAILED","description":"bad"}],'
            . '"fieldErrors":[{"field":"title","code":"NotBlank","message":"must not be blank"}]}',
        );
        try {
            (new PaymentLinksResource(Sdk::http($fake)))->create(new CreatePaymentLinkRequest(title: 'x'));
            self::fail('expected ValidationError');
        } catch (ValidationError $e) {
            self::assertSame('VALIDATION_FAILED', $e->errorCode);
            self::assertSame('title', $e->fieldErrors[0]->field);
        }
    }

    public function testUpdateRequestRejectsABlankTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new UpdatePaymentLinkRequest(title: ' ');
    }

    public function testEmptyUpdateSendsAnEmptyObject(): void
    {
        $fake = (new FakeClient())->reply(200, self::RESPONSE);
        (new PaymentLinksResource(Sdk::http($fake)))->update(self::ID, new UpdatePaymentLinkRequest());
        self::assertSame('{}', (string) $fake->lastRequest()->getBody());
    }
}
