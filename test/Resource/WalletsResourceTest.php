<?php

declare(strict_types=1);

namespace Danipa\Sdk\Tests\Resource;

use Danipa\Sdk\Http\HttpClient;
use Danipa\Sdk\Resource\WalletsResource;
use Danipa\Sdk\Tests\Support\FakeClient;
use PHPUnit\Framework\TestCase;

final class WalletsResourceTest extends TestCase
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

    public function testGetBalanceWithoutCurrency(): void
    {
        $fake = (new FakeClient())->reply(200, '{"merchantId":"m_1","currency":"GHS","available":"100.00","pending":"5.00","asOf":"2026-05-01T00:00:00Z"}');
        $resource = new WalletsResource($this->http($fake));

        $balance = $resource->getBalance();

        self::assertSame('100.00', $balance->available);
        self::assertSame(
            'https://api.sandbox.danipa.com/ms/v1/wallets/balance',
            (string) $fake->lastRequest()->getUri(),
        );
    }

    public function testGetBalanceWithCurrency(): void
    {
        $fake = (new FakeClient())->reply(200, '{"merchantId":"m_1","currency":"USD","available":"200.00","pending":"0","asOf":"2026-05-01T00:00:00Z"}');
        $resource = new WalletsResource($this->http($fake));

        $balance = $resource->getBalance('USD');

        self::assertSame('USD', $balance->currency);
        self::assertSame(
            'https://api.sandbox.danipa.com/ms/v1/wallets/balance?currency=USD',
            (string) $fake->lastRequest()->getUri(),
        );
    }
}
