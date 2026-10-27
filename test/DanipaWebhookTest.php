<?php

declare(strict_types=1);

namespace Danipa\Sdk\Tests;

use Danipa\Sdk\DanipaWebhook;
use Danipa\Sdk\Errors\WebhookVerificationError;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DanipaWebhookTest extends TestCase
{
    private const SECRET = 'whsec_test_secret_for_unit_tests';
    private const OLD_SECRET = 'whsec_previous_secret';
    private const PAYLOAD = '{"event":"payment.completed","amount":100.50}';
    private const NOW = 1_800_000_000;
    /** Vendored copy of sdks/fixtures/webhook-hmac.json (CI checks it for drift). */
    private const FIXTURE_PATH = __DIR__ . '/fixtures/webhook-hmac.json';

    private static function sig(string $payload = self::PAYLOAD, string $secret = self::SECRET, int $ts = self::NOW): string
    {
        return DanipaWebhook::computeSignature($payload, $secret, $ts);
    }

    // ---- verify ----------------------------------------------------------

    public function testValidSignatureAccepted(): void
    {
        self::assertTrue(DanipaWebhook::verify(self::PAYLOAD, self::sig(), (string) self::NOW, self::SECRET, now: self::NOW));
    }

    public function testValidSignatureAcceptedAgainstTheRealClock(): void
    {
        $ts = time();
        self::assertTrue(DanipaWebhook::verify(self::PAYLOAD, self::sig(ts: $ts), (string) $ts, self::SECRET));
    }

    public function testTamperedPayloadRejected(): void
    {
        self::assertFalse(DanipaWebhook::verify(
            str_replace('100.50', '999.50', self::PAYLOAD),
            self::sig(),
            (string) self::NOW,
            self::SECRET,
            now: self::NOW,
        ));
    }

    public function testInvalidSignatureRejected(): void
    {
        self::assertFalse(DanipaWebhook::verify(self::PAYLOAD, 'sha256=0000', (string) self::NOW, self::SECRET, now: self::NOW));
    }

    public function testRawBytesStreamIsAccepted(): void
    {
        $stream = Utils::streamFor(self::PAYLOAD);
        $stream->read(5); // a framework may already have read part of it
        self::assertTrue(DanipaWebhook::verify($stream, self::sig(), (string) self::NOW, self::SECRET, now: self::NOW));
    }

    public function testNonUtf8BytesVerify(): void
    {
        $bytes = "{\"b\":\"\xff\xfe\x00\"}";
        self::assertTrue(DanipaWebhook::verify($bytes, self::sig($bytes), (string) self::NOW, self::SECRET, now: self::NOW));
    }

    public function testDefaultToleranceIsFiveMinutes(): void
    {
        $old = self::NOW - 300;
        self::assertTrue(DanipaWebhook::verify(self::PAYLOAD, self::sig(ts: $old), (string) $old, self::SECRET, now: self::NOW));
        $older = self::NOW - 301;
        self::assertFalse(DanipaWebhook::verify(self::PAYLOAD, self::sig(ts: $older), (string) $older, self::SECRET, now: self::NOW));
        $future = self::NOW + 301;
        self::assertFalse(DanipaWebhook::verify(self::PAYLOAD, self::sig(ts: $future), (string) $future, self::SECRET, now: self::NOW));
    }

    public function testToleranceIsConfigurable(): void
    {
        $ts = self::NOW - 600;
        self::assertFalse(DanipaWebhook::verify(self::PAYLOAD, self::sig(ts: $ts), (string) $ts, self::SECRET, now: self::NOW));
        self::assertTrue(DanipaWebhook::verify(self::PAYLOAD, self::sig(ts: $ts), (string) $ts, self::SECRET, 900, self::NOW));
        self::assertFalse(DanipaWebhook::verify(self::PAYLOAD, self::sig(ts: self::NOW - 20), (string) (self::NOW - 20), self::SECRET, 10, self::NOW));
    }

    public function testNegativeToleranceRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DanipaWebhook::verify(self::PAYLOAD, self::sig(), (string) self::NOW, self::SECRET, -1, self::NOW);
    }

    public function testSecretRotationAcceptsAnyListedSecret(): void
    {
        $signedWithOld = self::sig(secret: self::OLD_SECRET);
        self::assertFalse(DanipaWebhook::verify(self::PAYLOAD, $signedWithOld, (string) self::NOW, self::SECRET, now: self::NOW));
        self::assertTrue(DanipaWebhook::verify(
            self::PAYLOAD,
            $signedWithOld,
            (string) self::NOW,
            [self::SECRET, self::OLD_SECRET],
            now: self::NOW,
        ));
        self::assertFalse(DanipaWebhook::verify(self::PAYLOAD, $signedWithOld, (string) self::NOW, [], now: self::NOW));
    }

    public function testEmptyInputsRejected(): void
    {
        self::assertFalse(DanipaWebhook::verify('', 'sig', '123', self::SECRET));
        self::assertFalse(DanipaWebhook::verify(self::PAYLOAD, '', '123', self::SECRET));
        self::assertFalse(DanipaWebhook::verify(self::PAYLOAD, 'sig', '', self::SECRET));
        self::assertFalse(DanipaWebhook::verify(self::PAYLOAD, 'sig', '123', ''));
    }

    public function testFalsyButNonEmptyPayloadIsVerifiedNotRejected(): void
    {
        // empty('0') is true — the old check silently rejected a valid "0" body.
        self::assertTrue(DanipaWebhook::verify('0', self::sig('0'), (string) self::NOW, self::SECRET, now: self::NOW));
    }

    public function testNonNumericTimestampRejected(): void
    {
        self::assertFalse(DanipaWebhook::verify(self::PAYLOAD, self::sig(), 'not-a-number', self::SECRET, now: self::NOW));
        self::assertFalse(DanipaWebhook::verify(self::PAYLOAD, self::sig(), '-5', self::SECRET, now: self::NOW));
    }

    public function testSignatureFormatIsSha256Hex(): void
    {
        self::assertMatchesRegularExpression('/^sha256=[0-9a-f]{64}$/', self::sig());
        self::assertNotSame(self::sig(ts: 1700000000), self::sig(ts: 1700000001));
    }

    // ---- constructEvent --------------------------------------------------

    /** @return array<string, string> */
    private static function headers(?string $sig = null): array
    {
        return [
            'X-Danipa-Signature' => $sig ?? self::sig(),
            'X-Danipa-Timestamp' => (string) self::NOW,
            'X-Danipa-Event' => 'payment.completed',
            'X-Danipa-Delivery' => 'd-42',
        ];
    }

    public function testConstructEventReturnsATypedEvent(): void
    {
        $event = DanipaWebhook::constructEvent(self::PAYLOAD, self::headers(), self::SECRET, now: self::NOW);

        self::assertSame('payment.completed', $event->type);
        self::assertSame('d-42', $event->deliveryId);
        self::assertSame(self::NOW, $event->timestamp);
        self::assertSame(['event' => 'payment.completed', 'amount' => '100.50'], $event->data, 'money parsed exactly');
        self::assertSame(self::PAYLOAD, $event->payload);
    }

    public function testConstructEventHeaderLookupIsCaseInsensitiveAndAcceptsLists(): void
    {
        $h = self::headers();
        $symfonyStyle = [];
        foreach ($h as $k => $v) {
            $symfonyStyle[strtolower($k)] = [$v];
        }
        $event = DanipaWebhook::constructEvent(self::PAYLOAD, $symfonyStyle, self::SECRET, now: self::NOW);
        self::assertSame('d-42', $event->deliveryId);
    }

    public function testConstructEventTreatsAnEmptyHeaderListAsMissing(): void
    {
        $h = self::headers();
        $h['X-Danipa-Delivery'] = [];
        $this->expectException(WebhookVerificationError::class);
        $this->expectExceptionMessage('X-Danipa-Delivery');
        DanipaWebhook::constructEvent(self::PAYLOAD, $h, self::SECRET, now: self::NOW);
    }

    public function testConstructEventAcceptsServerSuperglobalStyle(): void
    {
        $server = [];
        foreach (self::headers() as $k => $v) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $k))] = $v;
        }
        $event = DanipaWebhook::constructEvent(self::PAYLOAD, $server, self::SECRET, now: self::NOW);
        self::assertSame('payment.completed', $event->type);
    }

    public function testConstructEventAcceptsAPsr7Request(): void
    {
        $request = new ServerRequest('POST', '/webhooks/danipa', self::headers(), self::PAYLOAD);
        $event = DanipaWebhook::constructEvent($request->getBody(), $request, [self::OLD_SECRET, self::SECRET], now: self::NOW);
        self::assertSame('d-42', $event->deliveryId);
    }

    public function testConstructEventRejectsABadSignature(): void
    {
        $this->expectException(WebhookVerificationError::class);
        DanipaWebhook::constructEvent(self::PAYLOAD, self::headers('sha256=' . str_repeat('0', 64)), self::SECRET, now: self::NOW);
    }

    public function testConstructEventRejectsASignedJsonArrayRoot(): void
    {
        foreach (['[]', '[1,2]'] as $payload) {
            try {
                DanipaWebhook::constructEvent($payload, self::headers(self::sig($payload)), self::SECRET, now: self::NOW);
                self::fail('a JSON array root must be rejected: ' . $payload);
            } catch (WebhookVerificationError $e) {
                self::assertSame('Danipa webhook: body is not a JSON object', $e->getMessage());
            }
        }
    }

    public function testConstructEventAcceptsASignedEmptyObject(): void
    {
        $event = DanipaWebhook::constructEvent('{}', self::headers(self::sig('{}')), self::SECRET, now: self::NOW);
        self::assertSame([], $event->data);
        self::assertSame('{}', $event->payload);
    }

    public function testConstructEventRejectsAStaleDelivery(): void
    {
        $this->expectException(WebhookVerificationError::class);
        DanipaWebhook::constructEvent(self::PAYLOAD, self::headers(), self::SECRET, now: self::NOW + 301);
    }

    /** @return iterable<string, array{string}> */
    public static function requiredHeaders(): iterable
    {
        foreach (['X-Danipa-Signature', 'X-Danipa-Timestamp', 'X-Danipa-Event', 'X-Danipa-Delivery'] as $h) {
            yield $h => [$h];
        }
    }

    #[DataProvider('requiredHeaders')]
    public function testConstructEventRequiresHeader(string $header): void
    {
        $h = self::headers();
        unset($h[$header]);
        $this->expectException(WebhookVerificationError::class);
        $this->expectExceptionMessage($header);
        DanipaWebhook::constructEvent(self::PAYLOAD, $h, self::SECRET, now: self::NOW);
    }

    public function testConstructEventRejectsANonObjectBody(): void
    {
        $this->expectException(WebhookVerificationError::class);
        DanipaWebhook::constructEvent('[1]', self::headers(self::sig('[1]')), self::SECRET, now: self::NOW);
    }

    // ---- cross-SDK fixture ------------------------------------------------

    /**
     * Every Danipa SDK MUST produce the exact `expectedSignature` for each
     * vector in the shared fixture. Drift means a webhook validates in one
     * SDK and fails in another — a release blocker.
     */
    #[DataProvider('crossSdkVectorProvider')]
    public function testCrossSdkFixtureParity(string $name, string $payload, string $secret, int $timestamp, string $expected): void
    {
        self::assertSame(
            $expected,
            DanipaWebhook::computeSignature($payload, $secret, $timestamp),
            sprintf('Vector "%s" signature mismatch', $name),
        );
        if ($payload !== '') {
            self::assertTrue(DanipaWebhook::verify($payload, $expected, (string) $timestamp, $secret, now: $timestamp));
        }
    }

    /** @return iterable<string, array{string, string, string, int, string}> */
    public static function crossSdkVectorProvider(): iterable
    {
        $raw = file_get_contents(self::FIXTURE_PATH);
        if ($raw === false) {
            throw new \RuntimeException('Could not read fixture: ' . self::FIXTURE_PATH);
        }
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!\is_array($decoded) || !isset($decoded['vectors']) || !\is_array($decoded['vectors'])) {
            throw new \RuntimeException('Fixture missing "vectors" array');
        }
        foreach ($decoded['vectors'] as $vector) {
            if (
                !\is_array($vector)
                || !\is_string($vector['name'] ?? null)
                || !\is_string($vector['payload'] ?? null)
                || !\is_string($vector['secret'] ?? null)
                || !\is_int($vector['timestamp'] ?? null)
                || !\is_string($vector['expectedSignature'] ?? null)
            ) {
                throw new \RuntimeException('Malformed fixture vector');
            }
            yield $vector['name'] => [
                $vector['name'],
                $vector['payload'],
                $vector['secret'],
                $vector['timestamp'],
                $vector['expectedSignature'],
            ];
        }
    }

    // ---- dedupeKey (#7614) ------------------------------------------------
    // Danipa re-signs every retry with a fresh timestamp, so only the body is
    // stable across attempts; X-Danipa-Event / X-Danipa-Delivery are unsigned.

    /** @return array<string, string> */
    private static function signedAt(string $payload, int $ts, string $delivery = 'd-42'): array
    {
        return [
            'X-Danipa-Signature' => self::sig($payload, self::SECRET, $ts),
            'X-Danipa-Timestamp' => (string) $ts,
            'X-Danipa-Event' => 'payment.completed',
            'X-Danipa-Delivery' => $delivery,
        ];
    }

    public function testDedupeKeyIsStableAcrossRetriesResignedWithAFreshTimestamp(): void
    {
        $first = DanipaWebhook::constructEvent(self::PAYLOAD, self::signedAt(self::PAYLOAD, self::NOW - 120), self::SECRET, now: self::NOW);
        $retry = DanipaWebhook::constructEvent(
            Utils::streamFor(self::PAYLOAD),
            self::signedAt(self::PAYLOAD, self::NOW, 'd-other'),
            self::SECRET,
            now: self::NOW,
        );
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $first->dedupeKey);
        self::assertSame($first->dedupeKey, $retry->dedupeKey);
    }

    public function testDedupeKeyDiffersForADifferentBody(): void
    {
        $other = '{"event":"payment.completed","amount":100.51}';
        $a = DanipaWebhook::constructEvent(self::PAYLOAD, self::signedAt(self::PAYLOAD, self::NOW), self::SECRET, now: self::NOW);
        $b = DanipaWebhook::constructEvent($other, self::signedAt($other, self::NOW), self::SECRET, now: self::NOW);
        self::assertNotSame($a->dedupeKey, $b->dedupeKey);
    }

    #[DataProvider('crossSdkDedupeKeyProvider')]
    public function testCrossSdkDedupeKeyParity(string $name, string $payload, string $expected): void
    {
        foreach ([self::NOW - 60, self::NOW] as $ts) {
            $event = DanipaWebhook::constructEvent($payload, self::signedAt($payload, $ts), self::SECRET, now: self::NOW);
            self::assertSame($expected, $event->dedupeKey, sprintf('Vector "%s" @%d', $name, $ts));
        }
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function crossSdkDedupeKeyProvider(): iterable
    {
        $raw = file_get_contents(self::FIXTURE_PATH);
        if ($raw === false) {
            throw new \RuntimeException('Could not read fixture: ' . self::FIXTURE_PATH);
        }
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $vectors = \is_array($decoded) && \is_array($decoded['dedupeKey'] ?? null) ? ($decoded['dedupeKey']['vectors'] ?? null) : null;
        if (!\is_array($vectors) || $vectors === []) {
            throw new \RuntimeException('Fixture missing "dedupeKey.vectors" array');
        }
        foreach ($vectors as $vector) {
            if (
                !\is_array($vector)
                || !\is_string($vector['name'] ?? null)
                || !\is_string($vector['payload'] ?? null)
                || !\is_string($vector['expectedDigest'] ?? null)
            ) {
                throw new \RuntimeException('Malformed dedupeKey fixture vector');
            }
            yield $vector['name'] => [$vector['name'], $vector['payload'], $vector['expectedDigest']];
        }
    }
}
