<?php

declare(strict_types=1);

namespace Danipa\Sdk\Tests;

use Danipa\Sdk\Danipa;
use Danipa\Sdk\Tests\Support\FakeClient;
use PHPUnit\Framework\TestCase;

final class DanipaBuilderTest extends TestCase
{
    public function testApiKeyIsRequired(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('apiKey is required');
        Danipa::builder()->build();
    }

    public function testEmptyApiKeyIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Danipa::builder()->apiKey('')->build();
    }

    public function testBuiltClientTalksThroughTheInjectedTransport(): void
    {
        $fake = (new FakeClient())->reply(200, '{"id":"m_1","businessName":"Acme"}');
        $danipa = Danipa::builder()
            ->apiKey('dk_test_secret')
            ->baseUrl('https://example.test/ms/')
            ->transport($fake)
            ->build();

        $merchant = $danipa->merchants()->me();

        self::assertSame('Acme', $merchant->businessName);
        self::assertSame('https://example.test/ms/v1/merchants/me', (string) $fake->lastRequest()->getUri());
        self::assertSame('dk_test_secret', $fake->lastRequest()->getHeaderLine('X-API-Key'));
    }

    public function testMaxRetriesFromTheBuilderIsHonoured(): void
    {
        $fake = (new FakeClient())->reply(503);
        $danipa = Danipa::builder()->apiKey('dk_test_x')->transport($fake)->maxRetries(0)->build();
        try {
            $danipa->merchants()->me();
            self::fail('expected ServerError');
        } catch (\Danipa\Sdk\Errors\ServerError) {
        }
        self::assertCount(1, $fake->sent());
    }

    public function testNegativeMaxRetriesRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Danipa::builder()->apiKey('dk_test_x')->maxRetries(-1)->build();
    }

    public function testBuildWithoutTransportDiscoversOne(): void
    {
        // php-http/discovery finds Guzzle (require-dev); it throws if nothing is installed.
        $this->expectNotToPerformAssertions();
        Danipa::builder()->apiKey('dk_test_secret')->build();
    }
}
