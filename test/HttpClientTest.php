<?php

declare(strict_types=1);

namespace Danipa\Sdk\Tests;

use Danipa\Sdk\Errors\DanipaApiError;
use Danipa\Sdk\Errors\DanipaNetworkError;
use Danipa\Sdk\Http\HttpClient;
use Danipa\Sdk\RequestOptions;
use Danipa\Sdk\Tests\Support\FakeClient;
use PHPUnit\Framework\TestCase;

final class HttpClientTest extends TestCase
{
    private function client(FakeClient $fake, int $maxRetries = 3): HttpClient
    {
        return new HttpClient(
            apiKey: 'dk_test_secret',
            baseUrl: 'https://api.sandbox.danipa.com/ms',
            timeoutMs: 5000,
            maxRetries: $maxRetries,
            client: $fake,
            sleeper: static fn (int $ms): null => null,
        );
    }

    public function testPostSendsBearerAuthAcceptUserAgentAndJsonBody(): void
    {
        $fake = (new FakeClient())->reply(200, '{"id":"col_1","merchantId":"m_1","amount":"125.00","currency":"GHS","status":"PENDING","payer":{"phone":"+233244112233"},"paymentLinkId":null,"createdAt":"2026-05-01T12:00:00Z","completedAt":null}');
        $http = $this->client($fake);

        $body = $http->request('POST', '/v1/collections', ['amount' => '125.00']);

        self::assertSame('col_1', $body['id']);
        $req = $fake->lastRequest();
        self::assertSame('POST', $req->getMethod());
        self::assertSame('Bearer dk_test_secret', $req->getHeaderLine('Authorization'));
        self::assertSame('application/json', $req->getHeaderLine('Accept'));
        self::assertSame('application/json', $req->getHeaderLine('Content-Type'));
        self::assertStringStartsWith('danipa-php-sdk/', $req->getHeaderLine('User-Agent'));
        self::assertSame('https://api.sandbox.danipa.com/ms/v1/collections', (string) $req->getUri());
        self::assertSame('{"amount":"125.00"}', (string) $req->getBody());
    }

    public function testIdempotencyKeyIsForwardedOnPostOnly(): void
    {
        $fake = (new FakeClient())
            ->reply(200, '{"id":"col_1"}')
            ->reply(200, '{"id":"col_2"}');
        $http = $this->client($fake);

        $http->request('POST', '/v1/collections', ['x' => 1], [], RequestOptions::withIdempotencyKey('order-1234'));
        self::assertSame('order-1234', $fake->sent()[0]->getHeaderLine('X-Idempotency-Key'));

        // GET must NOT carry an idempotency key even if the caller sets one.
        $http->request('GET', '/v1/collections/col_1', null, [], RequestOptions::withIdempotencyKey('order-1234'));
        self::assertSame('', $fake->sent()[1]->getHeaderLine('X-Idempotency-Key'));
    }

    public function testFourXxThrowsImmediatelyAndDoesNotRetry(): void
    {
        $fake = (new FakeClient())->reply(400, '{"correlationId":"cor_x","errors":[{"code":"VALIDATION","description":"amount required"}]}');
        $http = $this->client($fake);

        try {
            $http->request('POST', '/v1/collections', ['x' => 1]);
            self::fail('expected DanipaApiError');
        } catch (DanipaApiError $e) {
            self::assertSame(400, $e->status);
            self::assertSame('VALIDATION', $e->errorCode);
            self::assertSame('amount required', $e->getMessage());
            self::assertSame('cor_x', $e->correlationId);
        }
        self::assertCount(1, $fake->sent(), '4xx must not retry');
    }

    public function testFiveXxRetriesUpToBudgetThenThrows(): void
    {
        $fake = (new FakeClient())
            ->reply(503, '{"errors":[{"code":"UPSTREAM_TIMEOUT","description":"upstream timeout"}]}')
            ->reply(503, '{"errors":[{"code":"UPSTREAM_TIMEOUT","description":"upstream timeout"}]}')
            ->reply(503, '{"errors":[{"code":"UPSTREAM_TIMEOUT","description":"upstream timeout"}]}');
        $http = $this->client($fake, maxRetries: 3);

        try {
            $http->request('POST', '/v1/collections', ['x' => 1]);
            self::fail('expected DanipaApiError');
        } catch (DanipaApiError $e) {
            self::assertSame(503, $e->status);
        }
        self::assertCount(3, $fake->sent(), '5xx must retry up to maxRetries');
    }

    public function testFiveXxRecoversWithinBudget(): void
    {
        $fake = (new FakeClient())
            ->reply(503, '{"errors":[{"code":"UPSTREAM","description":"flaky"}]}')
            ->reply(200, '{"id":"col_2"}');
        $http = $this->client($fake, maxRetries: 3);

        $body = $http->request('POST', '/v1/collections', ['x' => 1]);
        self::assertSame('col_2', $body['id']);
        self::assertCount(2, $fake->sent());
    }

    public function testNetworkErrorRetriesThenWraps(): void
    {
        $fake = (new FakeClient())
            ->throwTransportError('connection reset')
            ->throwTransportError('connection reset')
            ->throwTransportError('connection reset');
        $http = $this->client($fake, maxRetries: 3);

        try {
            $http->request('POST', '/v1/collections', ['x' => 1]);
            self::fail('expected DanipaNetworkError');
        } catch (DanipaNetworkError $e) {
            self::assertStringContainsString('/v1/collections', $e->getMessage());
            self::assertNotNull($e->getPrevious());
        }
        self::assertCount(3, $fake->sent());
    }

    public function testUnparseableErrorBodyStillProducesUsefulException(): void
    {
        $fake = (new FakeClient())->reply(500, '<html>nginx 502</html>', contentType: 'text/html');
        $http = $this->client($fake, maxRetries: 1);

        try {
            $http->request('POST', '/v1/collections', ['x' => 1]);
            self::fail('expected DanipaApiError');
        } catch (DanipaApiError $e) {
            self::assertSame(500, $e->status);
            self::assertSame('UNKNOWN', $e->errorCode);
            self::assertSame('HTTP 500', $e->getMessage());
            self::assertSame('<html>nginx 502</html>', $e->raw);
        }
    }

    public function testGetWithQueryAddsCurrencyParam(): void
    {
        $fake = (new FakeClient())->reply(200, '{"merchantId":"m_1","currency":"GHS","available":"0","pending":"0","asOf":"2026-05-01T00:00:00Z"}');
        $http = $this->client($fake);

        $http->request('GET', '/v1/wallets/balance', null, ['currency' => 'GHS']);

        self::assertSame(
            'https://api.sandbox.danipa.com/ms/v1/wallets/balance?currency=GHS',
            (string) $fake->lastRequest()->getUri(),
        );
    }

    public function testNullQueryValueIsSkipped(): void
    {
        $fake = (new FakeClient())->reply(200, '{"merchantId":"m_1","currency":"GHS","available":"0","pending":"0","asOf":"2026-05-01T00:00:00Z"}');
        $http = $this->client($fake);

        $http->request('GET', '/v1/wallets/balance', null, ['currency' => null, 'type' => 'wallet']);

        self::assertSame(
            'https://api.sandbox.danipa.com/ms/v1/wallets/balance?type=wallet',
            (string) $fake->lastRequest()->getUri(),
        );
    }

    public function testPathWithoutLeadingSlashIsAccepted(): void
    {
        $fake = (new FakeClient())->reply(200, '{"id":"col_1"}');
        $http = $this->client($fake, maxRetries: 1);

        $http->request('POST', 'v1/collections', ['x' => 1]);

        self::assertSame(
            'https://api.sandbox.danipa.com/ms/v1/collections',
            (string) $fake->lastRequest()->getUri(),
        );
    }

    public function testEmptyResponseBodyParsesAsEmptyArray(): void
    {
        $fake = (new FakeClient())->reply(204, '');
        $http = $this->client($fake, maxRetries: 1);

        $body = $http->request('POST', '/v1/collections', ['x' => 1]);
        self::assertSame([], $body);
    }

    public function testErrorBodyWithEmptyErrorsArrayUsesUnknownCode(): void
    {
        $fake = (new FakeClient())->reply(409, '{"correlationId":"cor_y","errors":[]}');
        $http = $this->client($fake, maxRetries: 1);

        try {
            $http->request('POST', '/v1/collections', ['x' => 1]);
            self::fail('expected DanipaApiError');
        } catch (DanipaApiError $e) {
            self::assertSame(409, $e->status);
            self::assertSame('UNKNOWN', $e->errorCode);
            self::assertSame('HTTP 409', $e->getMessage());
            self::assertSame('cor_y', $e->correlationId);
        }
    }

    public function testEmptyApiKeyRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HttpClient(apiKey: '');
    }

    public function testZeroMaxRetriesRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HttpClient(apiKey: 'dk_test_x', maxRetries: 0);
    }

    public function testSandboxBaseUrlInferredForTestKey(): void
    {
        self::assertSame(
            'https://api.sandbox.danipa.com/ms',
            HttpClient::inferBaseUrl('dk_test_anything'),
        );
    }

    public function testProductionBaseUrlInferredForLiveKey(): void
    {
        self::assertSame(
            'https://api.danipa.com/ms',
            HttpClient::inferBaseUrl('dk_live_anything'),
        );
    }

    public function testSandboxBaseUrlInferredForUnknownPrefix(): void
    {
        self::assertSame(
            'https://api.sandbox.danipa.com/ms',
            HttpClient::inferBaseUrl('totally_unknown_key'),
        );
    }
}
