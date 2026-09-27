<?php

declare(strict_types=1);

namespace Danipa\Sdk\Tests\Resource;

use Danipa\Sdk\Errors\PermissionError;
use Danipa\Sdk\Errors\ResponseParseError;
use Danipa\Sdk\Resource\MerchantsResource;
use Danipa\Sdk\Tests\Support\FakeClient;
use Danipa\Sdk\Tests\Support\Sdk;
use PHPUnit\Framework\TestCase;

final class MerchantsResourceTest extends TestCase
{
    private const BODY = <<<'JSON'
        {"id":"8c1f0a4e-1111-4222-8333-944455556666","businessName":"Kin & Calabash","businessType":"RESTAURANT",
         "country":"CA","status":"ACTIVE","kybStatus":"APPROVED","tier":"FREE","createdAt":"2026-05-01T12:00:00Z",
         "city":"Toronto","contactEmail":"ops@example.com","slug":"kin-and-calabash","publicCatalogEnabled":true,
         "defaultTaxRatePct":13.00,"effectiveDefaultTaxRatePct":13.000,"billingCurrency":"CAD",
         "checkoutMode":"DANIPA_CHECKOUT","gstHstNumber":"123456789RT0001","qstNumber":"1234567890TQ0001",
         "someFutureField":{"x":1}}
        JSON;

    public function testMeGetsTheMerchantProfile(): void
    {
        $fake = (new FakeClient())->reply(200, self::BODY, ['X-Correlation-Id' => 'cor-me']);
        $merchant = (new MerchantsResource(Sdk::http($fake)))->me();

        $req = $fake->lastRequest();
        self::assertSame('GET', $req->getMethod());
        self::assertSame(Sdk::BASE . '/v1/merchants/me', (string) $req->getUri());
        self::assertSame('dk_test_secret', $req->getHeaderLine('X-API-Key'));
        self::assertFalse($req->hasHeader('Authorization'));
        self::assertFalse($req->hasHeader('Idempotency-Key'));

        self::assertSame('8c1f0a4e-1111-4222-8333-944455556666', $merchant->id);
        self::assertSame('Kin & Calabash', $merchant->businessName);
        self::assertSame('CAD', $merchant->billingCurrency);
        self::assertTrue($merchant->publicCatalogEnabled);
        self::assertSame('13.00', $merchant->defaultTaxRatePct, 'decimal parsed exactly from a JSON number');
        self::assertSame('13.000', $merchant->effectiveDefaultTaxRatePct);
        self::assertNull($merchant->taxId, 'NON_NULL-absent fields are null');
        self::assertSame('123456789RT0001', $merchant->gstHstNumber);
        self::assertSame('1234567890TQ0001', $merchant->qstNumber);
        self::assertNotNull($merchant->meta);
        self::assertSame('cor-me', $merchant->meta->requestId);
    }

    public function testScopeDeniedIsAPermissionError(): void
    {
        $fake = (new FakeClient())->reply(403, '{"code":"API_KEY_SCOPE_DENIED","description":"API key access is not permitted for this request"}');
        $this->expectException(PermissionError::class);
        (new MerchantsResource(Sdk::http($fake)))->me();
    }

    public function testMissingIdIsAParseError(): void
    {
        $fake = (new FakeClient())->reply(200, '{"businessName":"x"}');
        $this->expectException(ResponseParseError::class);
        (new MerchantsResource(Sdk::http($fake)))->me();
    }

    public function testMistypedFieldIsAParseErrorNotATypeError(): void
    {
        $fake = (new FakeClient())->reply(200, '{"id":"m","publicCatalogEnabled":"yes"}');
        $this->expectException(ResponseParseError::class);
        (new MerchantsResource(Sdk::http($fake)))->me();
    }
}
