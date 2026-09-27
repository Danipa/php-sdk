<?php

declare(strict_types=1);

namespace Danipa\Sdk\Tests\Resource;

use Danipa\Sdk\Errors\PermissionError;
use Danipa\Sdk\Errors\ResponseParseError;
use Danipa\Sdk\Resource\BalancesResource;
use Danipa\Sdk\Tests\Support\FakeClient;
use Danipa\Sdk\Tests\Support\Sdk;
use PHPUnit\Framework\TestCase;

final class BalancesResourceTest extends TestCase
{
    public function testListGetsOneBalancePerCurrencyWithExactMoney(): void
    {
        $fake = (new FakeClient())->reply(
            200,
            '[{"id":"wa_1","currency":"GHS","availableBalance":1250.50,"pendingBalance":0.00,"status":"ACTIVE"},'
            . '{"id":"wa_2","currency":"CAD","availableBalance":10,"pendingBalance":2.25,"status":"ACTIVE"}]',
        );
        $balances = (new BalancesResource(Sdk::http($fake)))->list();

        $req = $fake->lastRequest();
        self::assertSame('GET', $req->getMethod());
        self::assertSame(Sdk::BASE . '/v1/merchants/me/wallet/balances', (string) $req->getUri());
        self::assertSame('dk_test_secret', $req->getHeaderLine('X-API-Key'));
        self::assertSame('', $req->getHeaderLine('Idempotency-Key'));
        self::assertCount(2, $balances);
        self::assertSame('GHS', $balances[0]->currency);
        self::assertSame('1250.50', $balances[0]->availableBalance);
        self::assertSame('0.00', $balances[0]->pendingBalance);
        self::assertSame('CAD', $balances[1]->currency);
        self::assertSame('10', $balances[1]->availableBalance);
        self::assertSame('ACTIVE', $balances[1]->status);
    }

    public function testNothingSettledYetIsAnEmptyList(): void
    {
        $fake = (new FakeClient())->reply(200, '[]');
        self::assertSame([], (new BalancesResource(Sdk::http($fake)))->list());
    }

    public function testAnObjectRootIsAParseError(): void
    {
        $fake = (new FakeClient())->reply(200, '{"currency":"GHS"}');
        $this->expectException(ResponseParseError::class);
        (new BalancesResource(Sdk::http($fake)))->list();
    }

    public function testAMissingCurrencyIsAParseError(): void
    {
        $fake = (new FakeClient())->reply(200, '[{"availableBalance":1.00}]');
        $this->expectException(ResponseParseError::class);
        (new BalancesResource(Sdk::http($fake)))->list();
    }

    public function testReadScopeDeniedIsAPermissionError(): void
    {
        $fake = (new FakeClient())->reply(403, '{"code":"API_KEY_SCOPE_DENIED","description":"missing payments:read"}');
        try {
            (new BalancesResource(Sdk::http($fake)))->list();
            self::fail('expected PermissionError');
        } catch (PermissionError $e) {
            self::assertSame('API_KEY_SCOPE_DENIED', $e->errorCode);
        }
    }
}
