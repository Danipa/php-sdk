<?php

declare(strict_types=1);

namespace Danipa\Sdk\Tests\Resource;

use Danipa\Sdk\Http\HttpClient;
use Danipa\Sdk\Model\CreatePaymentLinkRequest;
use Danipa\Sdk\Resource\PaymentLinksResource;
use Danipa\Sdk\Tests\Support\FakeClient;
use PHPUnit\Framework\TestCase;

final class PaymentLinksResourceTest extends TestCase
{
    private function http(FakeClient $fake): HttpClient
    {
        return new HttpClient(
            apiKey: 'dk_test_x',
            baseUrl: 'https://api.sandbox.danipa.com/ms',
            client: $fake,
            sleeper: static fn (int $ms): null => null,
        );
    }

    public function testCreatePostsToPaymentLinks(): void
    {
        $fake = (new FakeClient())->reply(200, '{"id":"pl_1","url":"https://pay.danipa.com/pl_1","amount":"125.00","currency":"GHS","description":"Coffee","expiresAt":null,"createdAt":"2026-05-01T12:00:00Z"}');
        $resource = new PaymentLinksResource($this->http($fake));

        $link = $resource->create(new CreatePaymentLinkRequest(
            amount: '125.00',
            currency: 'GHS',
            description: 'Coffee',
        ));

        self::assertSame('pl_1', $link->id);
        self::assertSame('Coffee', $link->description);
        self::assertStringEndsWith('/v1/merchants/me/payment-links', (string) $fake->lastRequest()->getUri());
    }
}
