<?php

declare(strict_types=1);

namespace Danipa\Sdk\Tests;

use Danipa\Sdk\Danipa;
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

    public function testBuildReturnsResourceAccessors(): void
    {
        $danipa = Danipa::builder()->apiKey('dk_test_secret')->build();
        self::assertNotNull($danipa->collections());
        self::assertNotNull($danipa->disbursements());
        self::assertNotNull($danipa->wallets());
        self::assertNotNull($danipa->paymentLinks());
        self::assertNotNull($danipa->invoices());
    }

    public function testInvalidMaxRetriesRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Danipa::builder()->apiKey('dk_test_x')->maxRetries(0)->build();
    }
}
