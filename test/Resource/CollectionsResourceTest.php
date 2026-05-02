<?php

declare(strict_types=1);

namespace Danipa\Sdk\Tests\Resource;

use Danipa\Sdk\Http\HttpClient;
use Danipa\Sdk\Model\CollectionStatus;
use Danipa\Sdk\Model\CreateCollectionRequest;
use Danipa\Sdk\Model\Payer;
use Danipa\Sdk\RequestOptions;
use Danipa\Sdk\Resource\CollectionsResource;
use Danipa\Sdk\Tests\Support\FakeClient;
use PHPUnit\Framework\TestCase;

final class CollectionsResourceTest extends TestCase
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

    public function testCreatePostsToCollectionsAndForwardsIdempotencyKey(): void
    {
        $fake = (new FakeClient())->reply(200, '{"id":"col_1","merchantId":"m_1","amount":"125.00","currency":"GHS","status":"PENDING","payer":{"phone":"+233244112233","name":"Ama K."},"paymentLinkId":null,"createdAt":"2026-05-01T12:00:00Z","completedAt":null}');
        $resource = new CollectionsResource($this->http($fake));

        $req = new CreateCollectionRequest(
            amount: '125.00',
            currency: 'GHS',
            payer: Payer::phone('+233244112233', 'Ama K.'),
        );
        $col = $resource->create($req, RequestOptions::withIdempotencyKey('order-1234'));

        self::assertSame('col_1', $col->id);
        self::assertSame(CollectionStatus::PENDING, $col->status);
        self::assertSame('+233244112233', $col->payer->phone);

        $sent = $fake->lastRequest();
        self::assertSame('POST', $sent->getMethod());
        self::assertStringEndsWith('/v1/collections', (string) $sent->getUri());
        self::assertSame('order-1234', $sent->getHeaderLine('X-Idempotency-Key'));
        $body = json_decode((string) $sent->getBody(), true);
        self::assertIsArray($body);
        self::assertSame('125.00', $body['amount']);
        self::assertSame('GHS', $body['currency']);
        self::assertSame(['phone' => '+233244112233', 'name' => 'Ama K.'], $body['payer']);
    }

    public function testGetEncodesIdAndCallsCorrectPath(): void
    {
        $fake = (new FakeClient())->reply(200, '{"id":"col 1","merchantId":"m_1","amount":"125.00","currency":"GHS","status":"COMPLETED","payer":{"phone":"+233"},"paymentLinkId":null,"createdAt":"2026-05-01T12:00:00Z","completedAt":"2026-05-01T12:01:00Z"}');
        $resource = new CollectionsResource($this->http($fake));

        $col = $resource->get('col 1');

        self::assertSame(CollectionStatus::COMPLETED, $col->status);
        self::assertSame(
            'https://api.sandbox.danipa.com/ms/v1/collections/col%201',
            (string) $fake->lastRequest()->getUri(),
        );
    }
}
