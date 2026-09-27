<?php

declare(strict_types=1);

namespace Danipa\Sdk\Tests;

use Danipa\Sdk\Danipa;
use Danipa\Sdk\Model\CreateInvoiceRequest;
use Danipa\Sdk\Model\CreatePaymentLinkRequest;
use Danipa\Sdk\Model\InvoiceLineItemRequest;
use Danipa\Sdk\Model\UpdatePaymentLinkRequest;
use Danipa\Sdk\Tests\Support\FakeClient;
use PHPUnit\Framework\TestCase;

/**
 * Contract: the SDK calls exactly the routes an API key can reach — the
 * route table in the fintech service's `ApiKeyAuthenticationFilter` — and
 * nothing else. Every public resource method is driven through a fake
 * transport and the (verb, path template) it sends is recorded; the set must
 * equal the table. Adding a resource method without a row here, or calling a
 * route the server denies to a key, fails this test.
 */
final class RouteContractTest extends TestCase
{
    /** The server's API-key route table (origin/develop, ApiKeyAuthenticationFilter.ROUTES). */
    private const SERVER_ROUTES = [
        'GET /v1/merchants/me',
        'POST /v1/merchants/me/payment-links',
        'GET /v1/merchants/me/payment-links',
        'PUT /v1/merchants/me/payment-links/{uuid}',
        'DELETE /v1/merchants/me/payment-links/{uuid}',
        'POST /v1/merchants/me/invoices',
        'GET /v1/merchants/me/invoices',
        'GET /v1/merchants/me/invoices/{uuid}',
        'POST /v1/merchants/me/invoices/{uuid}/send',
        'POST /v1/merchants/me/invoices/{uuid}/void',
        'GET /v1/merchants/me/wallet/balances',
    ];

    private const UUID = '3f2b8c1e-9a4d-4e2f-8b6a-1c2d3e4f5a6b';
    private const BASE = 'https://api.sandbox.danipa.com/ms';

    /**
     * One invocation per public resource method, keyed `resource::method`.
     *
     * @return array<string, array{string, \Closure(Danipa): mixed}>
     */
    private static function calls(): array
    {
        $invoice = '{"id":"' . self::UUID . '","status":"DRAFT"}';
        $link = '{"id":"' . self::UUID . '","status":"ACTIVE"}';
        $page = '{"content":[],"totalElements":0,"totalPages":0,"number":0,"size":20}';
        return [
            'merchants::me' => ['{"id":"m_1"}', static fn (Danipa $d) => $d->merchants()->me()],
            'paymentLinks::create' => [$link, static fn (Danipa $d) => $d->paymentLinks()->create(
                new CreatePaymentLinkRequest(title: 't'),
            )],
            'paymentLinks::list' => [$page, static fn (Danipa $d) => $d->paymentLinks()->list()],
            'paymentLinks::update' => [$link, static fn (Danipa $d) => $d->paymentLinks()->update(
                self::UUID,
                new UpdatePaymentLinkRequest(title: 't'),
            )],
            'paymentLinks::delete' => ['', static fn (Danipa $d) => $d->paymentLinks()->delete(self::UUID)],
            'invoices::create' => [$invoice, static fn (Danipa $d) => $d->invoices()->create(
                new CreateInvoiceRequest(customerName: 'c', lineItems: [new InvoiceLineItemRequest('a', 1, '1.00')]),
            )],
            'invoices::list' => [$page, static fn (Danipa $d) => $d->invoices()->list()],
            'invoices::get' => [$invoice, static fn (Danipa $d) => $d->invoices()->get(self::UUID)],
            'invoices::send' => [$invoice, static fn (Danipa $d) => $d->invoices()->send(self::UUID)],
            'invoices::void' => [$invoice, static fn (Danipa $d) => $d->invoices()->void(self::UUID)],
            'balances::list' => ['[]', static fn (Danipa $d) => $d->balances()->list()],
        ];
    }

    public function testEveryPublicResourceMethodIsCoveredByTheContract(): void
    {
        $declared = [];
        foreach ((new \ReflectionClass(Danipa::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $accessor) {
            $type = $accessor->getReturnType();
            if ($accessor->isStatic() || !$type instanceof \ReflectionNamedType) {
                continue;
            }
            $class = $type->getName();
            if (!str_starts_with($class, 'Danipa\\Sdk\\Resource\\') || !class_exists($class)) {
                continue;
            }
            foreach ((new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isConstructor() || $method->isStatic()) {
                    continue;
                }
                $declared[] = $accessor->getName() . '::' . $method->getName();
            }
        }
        $covered = array_keys(self::calls());
        sort($declared);
        sort($covered);
        self::assertSame($covered, $declared, 'a resource method was added or removed: update the route contract');
    }

    public function testTheSdkRouteSetEqualsTheServerApiKeyRouteTable(): void
    {
        $sent = [];
        foreach (self::calls() as $name => [$body, $call]) {
            $fake = (new FakeClient())->reply($body === '' ? 204 : 200, $body);
            $danipa = Danipa::builder()->apiKey('dk_test_contract')->transport($fake)->maxRetries(0)->build();
            $call($danipa);
            self::assertCount(1, $fake->sent(), "$name must send exactly one request");
            $req = $fake->lastRequest();
            $uri = (string) $req->getUri();
            self::assertStringStartsWith(self::BASE . '/', $uri, "$name: a dk_test_ key must reach sandbox");
            self::assertSame('dk_test_contract', $req->getHeaderLine('X-API-Key'), "$name: X-API-Key");
            self::assertFalse($req->hasHeader('Authorization'), "$name must never send Authorization");
            $path = (string) parse_url(substr($uri, \strlen(self::BASE)), PHP_URL_PATH);
            $sent[] = $req->getMethod() . ' ' . str_replace(self::UUID, '{uuid}', $path);
        }

        $expected = self::SERVER_ROUTES;
        sort($expected);
        sort($sent);
        self::assertSame($expected, $sent);
    }
}
