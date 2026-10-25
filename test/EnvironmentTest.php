<?php

declare(strict_types=1);

namespace Danipa\Sdk\Tests;

use Danipa\Sdk\Danipa;
use Danipa\Sdk\Environment;
use Danipa\Sdk\Errors\ConfigurationError;
use Danipa\Sdk\Tests\Support\FakeClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The environment (base URL) comes from the key prefix unless the caller says
 * otherwise. Precedence: explicit baseUrl > explicit environment > key prefix.
 */
final class EnvironmentTest extends TestCase
{
    private static function urlFor(string $apiKey, ?Environment $env = null, ?string $baseUrl = null): string
    {
        $fake = (new FakeClient())->reply(200, '{"id":"m_1"}');
        $builder = Danipa::builder()->apiKey($apiKey)->transport($fake);
        if ($env !== null) {
            $builder->environment($env);
        }
        if ($baseUrl !== null) {
            $builder->baseUrl($baseUrl);
        }
        $builder->build()->merchants()->me();
        return (string) $fake->lastRequest()->getUri();
    }

    public function testTestKeyGoesToSandbox(): void
    {
        self::assertSame('https://api.sandbox.danipa.com/ms/v1/merchants/me', self::urlFor('dk_test_abc'));
    }

    public function testLiveKeyIsRefusedUntilProductionLaunches(): void
    {
        self::assertSame('https://api.danipa.com/ms', Environment::LIVE->baseUrl());
        $this->expectException(ConfigurationError::class);
        $this->expectExceptionMessage('production is not live yet');
        self::urlFor('dk_live_abc');
    }

    public function testExplicitLiveEnvironmentIsRefusedUntilProductionLaunches(): void
    {
        $this->expectException(ConfigurationError::class);
        $this->expectExceptionMessage('production is not live yet');
        self::urlFor('dk_test_abc', Environment::LIVE);
    }

    /** @return iterable<string, array{string}> */
    public static function unknownPrefixes(): iterable
    {
        yield 'no prefix' => ['abc123'];
        yield 'other vendor style' => ['sk_test_abc'];
        yield 'wrong case' => ['DK_TEST_abc'];
        yield 'prefix without underscore' => ['dk_testabc'];
    }

    #[DataProvider('unknownPrefixes')]
    public function testUnknownPrefixFailsAtBuildWithAClearError(string $key): void
    {
        $fake = new FakeClient();
        try {
            Danipa::builder()->apiKey($key)->transport($fake)->build();
            self::fail('an unknown key prefix must not silently pick an environment');
        } catch (ConfigurationError $e) {
            self::assertStringContainsString('dk_test_', $e->getMessage());
            self::assertStringContainsString('dk_live_', $e->getMessage());
            self::assertStringNotContainsString($key, $e->getMessage(), 'the key must never be echoed');
        }
        self::assertSame([], $fake->sent());
    }

    public function testConfigurationErrorIsAnInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Danipa::builder()->apiKey('nope')->transport(new FakeClient())->build();
    }

    public function testExplicitBaseUrlOverridesTheKeyPrefix(): void
    {
        self::assertSame(
            'http://localhost:8080/ms/v1/merchants/me',
            self::urlFor('dk_live_abc', baseUrl: 'http://localhost:8080/ms/'),
        );
    }

    public function testExplicitEnvironmentOverridesTheKeyPrefix(): void
    {
        self::assertSame(
            'https://api.sandbox.danipa.com/ms/v1/merchants/me',
            self::urlFor('dk_live_abc', Environment::SANDBOX),
        );
    }

    public function testAnExplicitChoiceAllowsAnUnknownPrefix(): void
    {
        self::assertSame(
            'https://api.sandbox.danipa.com/ms/v1/merchants/me',
            self::urlFor('legacy', Environment::SANDBOX),
        );
        self::assertSame(
            'https://example.test/ms/v1/merchants/me',
            self::urlFor('legacy', baseUrl: 'https://example.test/ms'),
        );
    }

    public function testBaseUrlWinsOverEnvironment(): void
    {
        self::assertSame(
            'https://example.test/ms/v1/merchants/me',
            self::urlFor('dk_test_abc', Environment::LIVE, 'https://example.test/ms'),
        );
    }

    public function testBlankBaseUrlIsRejected(): void
    {
        $this->expectException(ConfigurationError::class);
        Danipa::builder()->apiKey('dk_test_abc')->baseUrl(' ')->transport(new FakeClient())->build();
    }

    public function testEnvironmentFromApiKey(): void
    {
        self::assertSame(Environment::SANDBOX, Environment::fromApiKey('dk_test_x'));
        self::assertSame(Environment::LIVE, Environment::fromApiKey('dk_live_x'));
    }
}
