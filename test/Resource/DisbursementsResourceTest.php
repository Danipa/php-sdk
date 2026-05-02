<?php

declare(strict_types=1);

namespace Danipa\Sdk\Tests\Resource;

use Danipa\Sdk\Http\HttpClient;
use Danipa\Sdk\Model\CreateDisbursementRequest;
use Danipa\Sdk\Model\DisbursementStatus;
use Danipa\Sdk\Model\Recipient;
use Danipa\Sdk\Resource\DisbursementsResource;
use Danipa\Sdk\Tests\Support\FakeClient;
use PHPUnit\Framework\TestCase;

final class DisbursementsResourceTest extends TestCase
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

    public function testCreatePostsToDisbursements(): void
    {
        $fake = (new FakeClient())->reply(200, '{"id":"dis_1","merchantId":"m_1","amount":"50.00","currency":"GHS","status":"PENDING","recipient":{"phone":"+233244999000","name":"Kojo"},"createdAt":"2026-05-01T12:00:00Z","completedAt":null}');
        $resource = new DisbursementsResource($this->http($fake));

        $dis = $resource->create(new CreateDisbursementRequest(
            amount: '50.00',
            currency: 'GHS',
            recipient: Recipient::phone('+233244999000', 'Kojo'),
        ));

        self::assertSame('dis_1', $dis->id);
        self::assertSame(DisbursementStatus::PENDING, $dis->status);
        self::assertStringEndsWith('/v1/disbursements', (string) $fake->lastRequest()->getUri());
    }

    public function testGetReturnsDisbursement(): void
    {
        $fake = (new FakeClient())->reply(200, '{"id":"dis_1","merchantId":"m_1","amount":"50.00","currency":"GHS","status":"COMPLETED","recipient":{"phone":"+233244999000"},"createdAt":"2026-05-01T12:00:00Z","completedAt":"2026-05-01T12:01:00Z"}');
        $resource = new DisbursementsResource($this->http($fake));

        $dis = $resource->get('dis_1');

        self::assertSame(DisbursementStatus::COMPLETED, $dis->status);
        self::assertStringEndsWith('/v1/disbursements/dis_1', (string) $fake->lastRequest()->getUri());
    }
}
