<?php

declare(strict_types=1);

namespace Danipa\Sdk\Tests;

use Composer\InstalledVersions;
use Danipa\Sdk\Errors\ConnectionError;
use Danipa\Sdk\Errors\DanipaNetworkError;
use Danipa\Sdk\Errors\RateLimitError;
use Danipa\Sdk\Errors\ResponseParseError;
use Danipa\Sdk\Errors\ServerError;
use Danipa\Sdk\Errors\TimeoutError;
use Danipa\Sdk\Errors\ValidationError;
use Danipa\Sdk\Http\HttpClient;
use Danipa\Sdk\RequestOptions;
use Danipa\Sdk\Tests\Support\Clock;
use Danipa\Sdk\Tests\Support\FakeClient;
use Danipa\Sdk\Tests\Support\InvalidRequestException;
use Danipa\Sdk\Tests\Support\Sdk;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

final class HttpClientTest extends TestCase
{
    private const UUID_V4 = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    // ---- auth + headers on the wire ------------------------------------

    public function testSendsXApiKeyAndNeverAuthorization(): void
    {
        $fake = (new FakeClient())->reply(200, '{"id":"m_1"}');
        Sdk::http($fake)->request('GET', '/v1/merchants/me');

        $req = $fake->lastRequest();
        self::assertSame('dk_test_secret', $req->getHeaderLine('X-API-Key'));
        self::assertFalse($req->hasHeader('Authorization'), 'Authorization + X-API-Key → 403 AMBIGUOUS_CREDENTIALS');
        self::assertFalse($req->hasHeader('X-Danipa-Environment'), 'the key prefix decides the environment');
        self::assertSame('application/json', $req->getHeaderLine('Accept'));
        self::assertSame('https://api.sandbox.danipa.com/ms/v1/merchants/me', (string) $req->getUri());
    }

    public function testUserAgentCarriesTheInstalledPackageVersionAndRuntime(): void
    {
        $fake = (new FakeClient())->reply(200, '{"id":"m_1"}');
        Sdk::http($fake)->request('GET', '/v1/merchants/me');

        $installed = InstalledVersions::getPrettyVersion('danipa/sdk');
        self::assertNotNull($installed);
        self::assertSame(
            sprintf('danipa-php/%s php/%s', ltrim($installed, 'v'), PHP_VERSION),
            $fake->lastRequest()->getHeaderLine('User-Agent'),
        );
    }

    public function testPostSerialisesJsonBody(): void
    {
        $fake = (new FakeClient())->reply(201, '{"id":"pl_1"}');
        Sdk::http($fake)->request('POST', '/v1/merchants/me/payment-links', ['title' => 'Tea', 'amount' => '10.50']);

        $req = $fake->lastRequest();
        self::assertSame('POST', $req->getMethod());
        self::assertSame('application/json', $req->getHeaderLine('Content-Type'));
        self::assertSame('{"title":"Tea","amount":"10.50"}', (string) $req->getBody());
    }

    // ---- idempotency ---------------------------------------------------

    /** @return iterable<string, array{string}> */
    public static function mutatingMethods(): iterable
    {
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $m) {
            yield $m => [$m];
        }
    }

    #[DataProvider('mutatingMethods')]
    public function testAnUnkeyedMutatingCallCannotHappen(string $method): void
    {
        $fake = (new FakeClient())->reply(200, '{}');
        $response = Sdk::http($fake)->request($method, '/v1/x', ['a' => 1]);

        $key = $fake->lastRequest()->getHeaderLine('Idempotency-Key');
        self::assertMatchesRegularExpression(self::UUID_V4, $key);
        self::assertFalse($fake->lastRequest()->hasHeader('X-Idempotency-Key'), 'legacy header must not be sent');
        self::assertSame($key, $response->meta->idempotencyKey);
    }

    public function testEachCallGetsItsOwnGeneratedKey(): void
    {
        $fake = (new FakeClient())->reply(200, '{}')->reply(200, '{}');
        $http = Sdk::http($fake);
        $http->request('POST', '/v1/x', []);
        $http->request('POST', '/v1/x', []);

        self::assertNotSame(
            $fake->sent()[0]->getHeaderLine('Idempotency-Key'),
            $fake->sent()[1]->getHeaderLine('Idempotency-Key'),
        );
    }

    public function testTheSameKeyIsReusedAcrossEveryRetryOfOneCall(): void
    {
        $fake = (new FakeClient())
            ->reply(503)
            ->throwTransportError()
            ->reply(200, '{}');
        $response = Sdk::http($fake)->request('POST', '/v1/x', ['a' => 1]);

        self::assertCount(3, $fake->sent());
        $keys = array_map(static fn (RequestInterface $r): string => $r->getHeaderLine('Idempotency-Key'), $fake->sent());
        self::assertCount(1, array_unique($keys), 'retries must reuse one key');
        self::assertMatchesRegularExpression(self::UUID_V4, $keys[0]);
        self::assertSame($keys[0], $response->meta->idempotencyKey);
        self::assertSame(3, $response->meta->attempts);
    }

    public function testCallerSuppliedKeyIsSentVerbatim(): void
    {
        $fake = (new FakeClient())->reply(200, '{}');
        Sdk::http($fake)->request('POST', '/v1/x', [], [], RequestOptions::withIdempotencyKey('order-1234'));

        self::assertSame('order-1234', $fake->lastRequest()->getHeaderLine('Idempotency-Key'));
    }

    public function testReadsCarryNoIdempotencyKey(): void
    {
        $fake = (new FakeClient())->reply(200, '{}');
        $response = Sdk::http($fake)->request('GET', '/v1/x', null, [], RequestOptions::withIdempotencyKey('ignored'));

        self::assertFalse($fake->lastRequest()->hasHeader('Idempotency-Key'));
        self::assertNull($response->meta->idempotencyKey);
    }

    public function testKeyLongerThan255CharactersIsRejectedBeforeSending(): void
    {
        self::assertSame(255, \strlen(str_repeat('k', 255)));
        new RequestOptions(str_repeat('k', 255)); // boundary is allowed

        $this->expectException(\InvalidArgumentException::class);
        RequestOptions::withIdempotencyKey(str_repeat('k', 256));
    }

    public function testBlankKeyIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RequestOptions::withIdempotencyKey('   ');
    }

    public function testReplayHeaderIsExposed(): void
    {
        $fake = (new FakeClient())->reply(201, '{}', ['X-Idempotent-Replay' => 'true']);
        $response = Sdk::http($fake)->request('POST', '/v1/x', []);

        self::assertTrue($response->meta->idempotentReplay);
    }

    public function testFreshResponseIsNotAReplay(): void
    {
        $fake = (new FakeClient())->reply(201, '{}');
        self::assertFalse(Sdk::http($fake)->request('POST', '/v1/x', [])->meta->idempotentReplay);
    }

    // ---- retries -------------------------------------------------------

    public function test429WithRetryAfterIsRetriedAfterExactlyThatDelay(): void
    {
        $clock = new Clock();
        $fake = (new FakeClient())
            ->reply(429, '{"code":"RATE_LIMIT_EXCEEDED","description":"slow down"}', ['Retry-After' => '2'])
            ->reply(200, '{}');
        Sdk::http($fake, $clock)->request('POST', '/v1/x', []);

        self::assertCount(2, $fake->sent());
        self::assertSame([2000], $clock->sleeps);
    }

    public function testRetryAfterIsCappedAtSixtySeconds(): void
    {
        $clock = new Clock();
        $fake = (new FakeClient())->reply(503, '', ['Retry-After' => '3600'])->reply(200, '{}');
        Sdk::http($fake, $clock)->request('GET', '/v1/x');

        self::assertSame([60000], $clock->sleeps);
    }

    public function testRetryAfterHttpDateFallsBackToBackoff(): void
    {
        $clock = new Clock();
        $fake = (new FakeClient())
            ->reply(503, '', ['Retry-After' => 'Wed, 21 Oct 2026 07:28:00 GMT'])
            ->reply(200, '{}');
        Sdk::http($fake, $clock)->request('GET', '/v1/x');

        self::assertSame([500], $clock->sleeps);
    }

    public function test429ExhaustingTheBudgetThrowsRateLimitErrorWithRetryAfter(): void
    {
        $clock = new Clock();
        $fake = (new FakeClient())
            ->reply(429, '{"code":"RATE_LIMIT_EXCEEDED","description":"slow down"}', ['Retry-After' => '60'])
            ->reply(429, '{"code":"RATE_LIMIT_EXCEEDED","description":"slow down"}', ['Retry-After' => '60'])
            ->reply(429, '{"code":"RATE_LIMIT_EXCEEDED","description":"slow down"}', ['Retry-After' => '60']);

        try {
            Sdk::http($fake, $clock, maxRetries: 2)->request('POST', '/v1/x', []);
            self::fail('expected RateLimitError');
        } catch (RateLimitError $e) {
            self::assertSame(429, $e->status);
            self::assertSame('RATE_LIMIT_EXCEEDED', $e->errorCode);
            self::assertSame(60, $e->retryAfter());
        }
        self::assertCount(3, $fake->sent(), 'maxRetries=2 → 1 attempt + 2 retries');
        self::assertSame([60000, 60000], $clock->sleeps);
    }

    /** @return iterable<string, array{int}> */
    public static function nonRetryableStatuses(): iterable
    {
        foreach ([500, 501, 400, 401, 403, 404, 409, 422] as $s) {
            yield (string) $s => [$s];
        }
    }

    #[DataProvider('nonRetryableStatuses')]
    public function testStatusIsNotRetried(int $status): void
    {
        $clock = new Clock();
        $fake = (new FakeClient())->reply($status, '{"code":"X","description":"no"}');
        try {
            Sdk::http($fake, $clock)->request('POST', '/v1/x', []);
            self::fail('expected an error');
        } catch (\Danipa\Sdk\Errors\DanipaApiError $e) {
            self::assertSame($status, $e->status);
        }
        self::assertCount(1, $fake->sent(), "$status must not be retried");
        self::assertSame([], $clock->sleeps);
    }

    public function test500IsNotRetriedAndSurfacesAsServerError(): void
    {
        $fake = (new FakeClient())->reply(500, '{"errors":[{"code":"INTERNAL","description":"boom"}]}');
        $this->expectException(ServerError::class);
        try {
            Sdk::http($fake)->request('POST', '/v1/x', []);
        } finally {
            self::assertCount(1, $fake->sent());
        }
    }

    /** @return iterable<string, array{int}> */
    public static function retryableStatuses(): iterable
    {
        foreach ([408, 429, 502, 503, 504] as $s) {
            yield (string) $s => [$s];
        }
    }

    #[DataProvider('retryableStatuses')]
    public function testTransientStatusIsRetried(int $status): void
    {
        $fake = (new FakeClient())->reply($status)->reply(200, '{"ok":true}');
        $response = Sdk::http($fake)->request('POST', '/v1/x', []);

        self::assertCount(2, $fake->sent());
        self::assertSame(200, $response->meta->status);
    }

    public function testBackoffIsFullJitterExponentialFromHalfASecondCappedAtEight(): void
    {
        $clock = new Clock();
        $fake = new FakeClient();
        for ($i = 0; $i < 7; $i++) {
            $fake->reply(503);
        }
        $ceilings = [];
        $random = static function (int $max) use (&$ceilings): int {
            $ceilings[] = $max;
            return $max; // worst case of the jitter
        };

        try {
            Sdk::http($fake, $clock, maxRetries: 6, random: $random)->request('GET', '/v1/x');
            self::fail('expected ServerError');
        } catch (ServerError) {
        }
        self::assertSame([500, 1000, 2000, 4000, 8000, 8000], $ceilings);
        self::assertSame([500, 1000, 2000, 4000, 8000, 8000], $clock->sleeps);
    }

    public function testJitterCanDrawZero(): void
    {
        $clock = new Clock();
        $fake = (new FakeClient())->reply(502)->reply(200, '{}');
        Sdk::http($fake, $clock, random: static fn (int $max): int => 0)->request('GET', '/v1/x');

        self::assertSame([0], $clock->sleeps);
    }

    public function testMaxRetriesZeroMeansASingleAttempt(): void
    {
        $fake = (new FakeClient())->reply(503);
        try {
            Sdk::http($fake, maxRetries: 0)->request('GET', '/v1/x');
            self::fail('expected ServerError');
        } catch (ServerError $e) {
            self::assertSame(503, $e->status);
        }
        self::assertCount(1, $fake->sent());
    }

    public function testNegativeMaxRetriesRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Sdk::http(new FakeClient(), maxRetries: -1);
    }

    public function testNetworkErrorIsRetriedThenSurfacesAsConnectionErrorWithTheKey(): void
    {
        $fake = (new FakeClient())
            ->throwTransportError('connection reset')
            ->throwTransportError('connection reset')
            ->throwTransportError('connection reset');

        try {
            Sdk::http($fake)->request('POST', '/v1/x', [], [], RequestOptions::withIdempotencyKey('k-1'));
            self::fail('expected ConnectionError');
        } catch (ConnectionError $e) {
            self::assertSame('k-1', $e->idempotencyKey);
            self::assertStringContainsString('3 attempts', $e->getMessage());
            self::assertNotNull($e->getPrevious());
        }
        self::assertCount(3, $fake->sent());
    }

    public function testTimeoutSurfacesAsTimeoutError(): void
    {
        $fake = (new FakeClient())->throwTransportError('cURL error 28: Operation timed out after 30001 milliseconds');
        $this->expectException(TimeoutError::class);
        Sdk::http($fake, maxRetries: 0)->request('GET', '/v1/x');
    }

    public function testUnsendableRequestIsNotRetried(): void
    {
        $fake = (new FakeClient())->fail(new InvalidRequestException('bad uri'));
        try {
            Sdk::http($fake)->request('GET', '/v1/x');
            self::fail('expected ConnectionError');
        } catch (ConnectionError) {
        }
        self::assertCount(1, $fake->sent());
    }

    // ---- 2xx that cannot be parsed --------------------------------------

    public function testUnparseable2xxIsAParseErrorNotANetworkError(): void
    {
        $fake = (new FakeClient())->reply(201, '<html>proxy page</html>', ['X-Request-Id' => 'req-9']);
        $response = Sdk::http($fake)->request('POST', '/v1/x', [], [], RequestOptions::withIdempotencyKey('k-2'));

        try {
            $response->parse(static fn (array $d): array => $d);
            self::fail('expected ResponseParseError');
        } catch (ResponseParseError $e) {
            // ResponseParseError extends DanipaError directly, never DanipaNetworkError
            // (PHPStan proves the network branch unreachable): the call may have succeeded.
            self::assertSame(201, $e->status);
            self::assertSame('<html>proxy page</html>', $e->body);
            self::assertSame('k-2', $e->idempotencyKey, 'caller needs the key to retry safely');
            self::assertSame('req-9', $e->requestId);
        }
        self::assertCount(1, $fake->sent(), 'a 2xx must never be retried');
    }

    public function testJsonArrayRootIsAParseError(): void
    {
        $fake = (new FakeClient())->reply(200, '[1,2]');
        $this->expectException(ResponseParseError::class);
        Sdk::http($fake)->request('GET', '/v1/x')->parse(static fn (array $d): array => $d);
    }

    public function testEmpty2xxBodyIsAParseErrorForAModel(): void
    {
        $fake = (new FakeClient())->reply(200, '');
        $this->expectException(ResponseParseError::class);
        Sdk::http($fake)->request('GET', '/v1/x')->parse(static fn (array $d): array => $d);
    }

    // ---- URL building + base URL -----------------------------------------

    public function testQueryStringSkipsNullsAndEncodesBooleans(): void
    {
        $fake = (new FakeClient())->reply(200, '{}');
        Sdk::http($fake)->request('GET', 'v1/x', null, ['a' => null, 'b' => true, 'c' => 'x y']);

        self::assertSame(Sdk::BASE . '/v1/x?b=true&c=x%20y', (string) $fake->lastRequest()->getUri());
    }

    public function testBaseUrlInferredFromKeyPrefix(): void
    {
        self::assertSame('https://api.sandbox.danipa.com/ms', HttpClient::inferBaseUrl('dk_test_abc'));
        self::assertSame('https://api.danipa.com/ms', HttpClient::inferBaseUrl('dk_live_abc'));
    }

    public function testUnknownKeyPrefixIsAConfigurationErrorNotASandboxGuess(): void
    {
        $this->expectException(\Danipa\Sdk\Errors\ConfigurationError::class);
        HttpClient::inferBaseUrl('other');
    }

    public function testLiveKeyTargetsProductionHost(): void
    {
        $fake = (new FakeClient())->reply(200, '{}');
        (new HttpClient(apiKey: 'dk_live_x', client: $fake, sleeper: static fn (int $ms) => null))
            ->request('GET', '/v1/merchants/me');

        self::assertSame('https://api.danipa.com/ms/v1/merchants/me', (string) $fake->lastRequest()->getUri());
    }

    public function testEmptyApiKeyRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HttpClient(apiKey: '');
    }

    public function testApiKeyWithWhitespaceRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HttpClient(apiKey: "dk_test_x\r\nAuthorization: Bearer y", client: new FakeClient());
    }

    // ---- transport discovery / injection ---------------------------------

    public function testTransportAndFactoriesAreDiscoveredWhenNotInjected(): void
    {
        // php-http/discovery finds Guzzle (require-dev); it throws if nothing is installed.
        $this->expectNotToPerformAssertions();
        new HttpClient(apiKey: 'dk_test_x');
    }

    public function testInjectedFactoriesAreUsed(): void
    {
        $inner = new HttpFactory();
        $factory = new class ($inner) implements RequestFactoryInterface, StreamFactoryInterface {
            public int $requests = 0;
            public int $streams = 0;

            public function __construct(private readonly HttpFactory $inner)
            {
            }

            public function createRequest(string $method, $uri): RequestInterface
            {
                $this->requests++;
                return $this->inner->createRequest($method, $uri);
            }

            public function createStream(string $content = ''): StreamInterface
            {
                $this->streams++;
                return $this->inner->createStream($content);
            }

            public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
            {
                return $this->inner->createStreamFromFile($filename, $mode);
            }

            public function createStreamFromResource($resource): StreamInterface
            {
                return $this->inner->createStreamFromResource($resource);
            }
        };
        $fake = (new FakeClient())->reply(200, '{}');
        (new HttpClient(
            apiKey: 'dk_test_x',
            client: $fake,
            requestFactory: $factory,
            streamFactory: $factory,
        ))->request('POST', '/v1/x', ['a' => 1]);

        self::assertSame(1, $factory->requests);
        self::assertSame(1, $factory->streams);
    }

    public function testValidationErrorIsNotRetried(): void
    {
        $fake = (new FakeClient())->reply(400, '{"code":"IDEMPOTENCY_KEY_TOO_LONG","description":"too long"}');
        $this->expectException(ValidationError::class);
        Sdk::http($fake)->request('POST', '/v1/x', []);
    }
}
