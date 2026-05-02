<?php

declare(strict_types=1);

namespace Danipa\Sdk\Tests;

use Danipa\Sdk\DanipaWebhook;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DanipaWebhookTest extends TestCase
{
    private const SECRET = 'whsec_test_secret_for_unit_tests';
    private const PAYLOAD = '{"event":"payment.completed","amount":100}';
    private const FIXTURE_PATH = __DIR__ . '/../../fixtures/webhook-hmac.json';

    public function testValidSignatureAccepted(): void
    {
        $ts = time();
        $sig = DanipaWebhook::computeSignature(self::PAYLOAD, self::SECRET, $ts);
        self::assertTrue(DanipaWebhook::verify(self::PAYLOAD, $sig, (string) $ts, self::SECRET));
    }

    public function testInvalidSignatureRejected(): void
    {
        $ts = time();
        self::assertFalse(
            DanipaWebhook::verify(self::PAYLOAD, 'sha256=0000', (string) $ts, self::SECRET),
        );
    }

    public function testExpiredTimestampRejected(): void
    {
        $ts = time() - 600;
        $sig = DanipaWebhook::computeSignature(self::PAYLOAD, self::SECRET, $ts);
        self::assertFalse(DanipaWebhook::verify(self::PAYLOAD, $sig, (string) $ts, self::SECRET));
    }

    public function testFutureTimestampRejected(): void
    {
        $ts = time() + 600;
        $sig = DanipaWebhook::computeSignature(self::PAYLOAD, self::SECRET, $ts);
        self::assertFalse(DanipaWebhook::verify(self::PAYLOAD, $sig, (string) $ts, self::SECRET));
    }

    public function testEmptyInputsRejected(): void
    {
        self::assertFalse(DanipaWebhook::verify('', 'sig', '123', self::SECRET));
        self::assertFalse(DanipaWebhook::verify(self::PAYLOAD, '', '123', self::SECRET));
        self::assertFalse(DanipaWebhook::verify(self::PAYLOAD, 'sig', '', self::SECRET));
        self::assertFalse(DanipaWebhook::verify(self::PAYLOAD, 'sig', '123', ''));
    }

    public function testNonNumericTimestampRejected(): void
    {
        self::assertFalse(DanipaWebhook::verify(self::PAYLOAD, 'sha256=abc', 'not-a-number', self::SECRET));
    }

    public function testSignatureFormatIsSha256Hex(): void
    {
        $sig = DanipaWebhook::computeSignature(self::PAYLOAD, self::SECRET, 1700000000);
        self::assertMatchesRegularExpression('/^sha256=[0-9a-f]{64}$/', $sig);
    }

    public function testDifferentTimestampsProduceDifferentSignatures(): void
    {
        $a = DanipaWebhook::computeSignature(self::PAYLOAD, self::SECRET, 1700000000);
        $b = DanipaWebhook::computeSignature(self::PAYLOAD, self::SECRET, 1700000001);
        self::assertNotSame($a, $b);
    }

    /**
     * Cross-SDK fixture parity. Every Danipa SDK (Node, Java, PHP, future
     * Python / Go) MUST produce the exact `expectedSignature` for each
     * vector in `sdks/fixtures/webhook-hmac.json`. Drift means a webhook
     * will validate in one SDK but reject in another — release-blocker.
     *
     * @param string $payload
     * @param string $secret
     * @param int    $timestamp
     * @param string $expected
     */
    #[DataProvider('crossSdkVectorProvider')]
    public function testCrossSdkFixtureParity(
        string $name,
        string $payload,
        string $secret,
        int $timestamp,
        string $expected,
    ): void {
        $actual = DanipaWebhook::computeSignature($payload, $secret, $timestamp);
        self::assertSame(
            $expected,
            $actual,
            sprintf('Vector "%s" signature mismatch — PHP disagrees with the canonical fixture', $name),
        );
    }

    /**
     * @return iterable<string, array{string, string, string, int, string}>
     */
    public static function crossSdkVectorProvider(): iterable
    {
        $raw = file_get_contents(self::FIXTURE_PATH);
        if ($raw === false) {
            throw new \RuntimeException('Could not read fixture: ' . self::FIXTURE_PATH);
        }
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || !isset($decoded['vectors']) || !is_array($decoded['vectors'])) {
            throw new \RuntimeException('Fixture missing "vectors" array');
        }
        foreach ($decoded['vectors'] as $vector) {
            \assert(\is_array($vector));
            $name = (string) ($vector['name'] ?? 'unnamed');
            yield $name => [
                $name,
                (string) $vector['payload'],
                (string) $vector['secret'],
                (int) $vector['timestamp'],
                (string) $vector['expectedSignature'],
            ];
        }
    }
}
