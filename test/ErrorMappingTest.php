<?php

declare(strict_types=1);

namespace Danipa\Sdk\Tests;

use Danipa\Sdk\Errors\AuthenticationError;
use Danipa\Sdk\Errors\ConflictError;
use Danipa\Sdk\Errors\DanipaApiError;
use Danipa\Sdk\Errors\NotFoundError;
use Danipa\Sdk\Errors\PermissionError;
use Danipa\Sdk\Errors\RateLimitError;
use Danipa\Sdk\Errors\ServerError;
use Danipa\Sdk\Errors\ValidationError;
use Danipa\Sdk\RequestOptions;
use Danipa\Sdk\Tests\Support\FakeClient;
use Danipa\Sdk\Tests\Support\Sdk;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ErrorMappingTest extends TestCase
{
    /** @param array<string, string> $headers */
    private function errorFor(int $status, string $body, array $headers = []): DanipaApiError
    {
        $fake = (new FakeClient())->reply($status, $body, $headers);
        try {
            Sdk::http($fake, maxRetries: 0)->request('POST', '/v1/x', [], [], RequestOptions::withIdempotencyKey('k-err'));
        } catch (DanipaApiError $e) {
            return $e;
        }
        self::fail('expected a DanipaApiError');
    }

    public function testApiStatusEnvelopeIsFullyParsed(): void
    {
        $e = $this->errorFor(400, (string) json_encode([
            'timeStamp' => 1711814400000,
            'correlationId' => 'cor-1',
            'traceId' => 'trace-1',
            'statusCode' => 400,
            'severity' => 'ERROR',
            'retryable' => false,
            'messageKey' => 'error.validation',
            'errors' => [['code' => 'VALIDATION_FAILED', 'description' => 'title is required']],
            'fieldErrors' => [['field' => 'title', 'code' => 'REQUIRED', 'message' => 'must not be blank']],
        ]));

        self::assertInstanceOf(ValidationError::class, $e);
        self::assertSame(400, $e->status);
        self::assertSame('VALIDATION_FAILED', $e->errorCode);
        self::assertSame('title is required', $e->getMessage());
        self::assertSame('cor-1', $e->requestId, 'body correlationId when no header');
        self::assertSame('cor-1', $e->correlationId);
        self::assertSame('trace-1', $e->traceId);
        self::assertSame('error.validation', $e->messageKey);
        self::assertFalse($e->retryable);
        self::assertCount(1, $e->fieldErrors);
        self::assertSame('title', $e->fieldErrors[0]->field);
        self::assertSame('REQUIRED', $e->fieldErrors[0]->code);
        self::assertSame('must not be blank', $e->fieldErrors[0]->message);
        self::assertSame('k-err', $e->idempotencyKey);
        self::assertIsArray($e->raw);
    }

    public function testFlatFilterShapeIsParsed(): void
    {
        $e = $this->errorFor(403, '{"code":"API_KEY_SCOPE_DENIED","description":"API key access is not permitted for this request"}');

        self::assertInstanceOf(PermissionError::class, $e);
        self::assertSame('API_KEY_SCOPE_DENIED', $e->errorCode);
        self::assertSame('API key access is not permitted for this request', $e->getMessage());
        self::assertSame([], $e->fieldErrors);
    }

    public function testCorrelationHeaderWinsOverBody(): void
    {
        $e = $this->errorFor(404, '{"correlationId":"body-id","errors":[{"code":"NOT_FOUND","description":"nope"}]}', [
            'X-Correlation-Id' => 'hdr-id',
        ]);
        self::assertInstanceOf(NotFoundError::class, $e);
        self::assertSame('hdr-id', $e->requestId);
    }

    public function testRequestIdHeaderIsUsedWhenNoCorrelationHeader(): void
    {
        $e = $this->errorFor(401, '{"code":"INVALID_API_KEY","description":"Invalid or expired API key"}', [
            'X-Request-Id' => 'req-7',
        ]);
        self::assertInstanceOf(AuthenticationError::class, $e);
        self::assertSame('req-7', $e->requestId);
    }

    public function testTraceIdIsTheLastRequestIdFallback(): void
    {
        $e = $this->errorFor(409, '{"traceId":"t-5","errors":[{"code":"IDEMPOTENCY_KEY_COLLISION","description":"reused"}]}');
        self::assertInstanceOf(ConflictError::class, $e);
        self::assertSame('IDEMPOTENCY_KEY_COLLISION', $e->errorCode);
        self::assertSame('t-5', $e->requestId);
    }

    public function testReplayedErrorIsFlagged(): void
    {
        $e = $this->errorFor(409, '{"code":"X"}', ['X-Idempotent-Replay' => 'true']);
        self::assertTrue($e->idempotentReplay);
    }

    public function testNonJsonBodyKeepsRawTextAndUnknownCode(): void
    {
        $e = $this->errorFor(500, '<html>nginx</html>', ['Content-Type' => 'text/html']);
        self::assertInstanceOf(ServerError::class, $e);
        self::assertSame('UNKNOWN', $e->errorCode);
        self::assertSame('HTTP 500', $e->getMessage());
        self::assertSame('<html>nginx</html>', $e->raw);
    }

    public function testEmptyErrorsArrayFallsBackToUnknown(): void
    {
        $e = $this->errorFor(422, '{"errors":[]}');
        self::assertInstanceOf(ValidationError::class, $e);
        self::assertSame('UNKNOWN', $e->errorCode);
        self::assertSame('HTTP 422', $e->getMessage());
    }

    public function testRetryAfterSecondsFromEnvelopeWhenNoHeader(): void
    {
        $e = $this->errorFor(429, '{"retryable":true,"retryAfterSeconds":7,"errors":[{"code":"RATE_LIMIT_EXCEEDED"}]}');
        self::assertInstanceOf(RateLimitError::class, $e);
        self::assertSame(7, $e->retryAfter());
        self::assertTrue($e->retryable);
    }

    /** @return iterable<string, array{int, class-string<DanipaApiError>}> */
    public static function statusClasses(): iterable
    {
        yield '400' => [400, ValidationError::class];
        yield '401' => [401, AuthenticationError::class];
        yield '402' => [402, DanipaApiError::class];
        yield '403' => [403, PermissionError::class];
        yield '404' => [404, NotFoundError::class];
        yield '409' => [409, ConflictError::class];
        yield '422' => [422, ValidationError::class];
        yield '429' => [429, RateLimitError::class];
        yield '500' => [500, ServerError::class];
        yield '503' => [503, ServerError::class];
    }

    /** @param class-string<DanipaApiError> $class */
    #[DataProvider('statusClasses')]
    public function testStatusMapsToTypedError(int $status, string $class): void
    {
        $e = $this->errorFor($status, '{"code":"C","description":"d"}');
        self::assertSame($class, $e::class);
        self::assertSame('C', $e->errorCode);
    }
}
