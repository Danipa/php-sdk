<?php

declare(strict_types=1);

namespace Danipa\Sdk;

use Danipa\Sdk\Http\HttpClient;
use Danipa\Sdk\Resource\BalancesResource;
use Danipa\Sdk\Resource\InvoicesResource;
use Danipa\Sdk\Resource\MerchantsResource;
use Danipa\Sdk\Resource\PaymentLinksResource;

/**
 * The main entry point. Build once and reuse — every resource shares the
 * same HTTP client (auth, idempotency, retries).
 *
 * ```php
 * $danipa = Danipa::builder()
 *     ->apiKey(getenv('DANIPA_API_KEY'))
 *     ->build();
 *
 * $merchant = $danipa->merchants()->me();
 * ```
 *
 * The API key is sent as `X-API-Key`; the host is inferred from its prefix:
 * `dk_test_…` → sandbox (`https://api.sandbox.danipa.com/ms`),
 * `dk_live_…` → production (`https://api.danipa.com/ms`, not yet live).
 * Any other prefix fails at build time with a {@see Errors\ConfigurationError}.
 * Override with {@see DanipaBuilder::environment()} or {@see DanipaBuilder::baseUrl()}.
 */
final class Danipa
{
    public function __construct(
        private readonly MerchantsResource $merchants,
        private readonly PaymentLinksResource $paymentLinks,
        private readonly InvoicesResource $invoices,
        private readonly BalancesResource $balances,
    ) {
    }

    public function merchants(): MerchantsResource
    {
        return $this->merchants;
    }

    public function paymentLinks(): PaymentLinksResource
    {
        return $this->paymentLinks;
    }

    public function invoices(): InvoicesResource
    {
        return $this->invoices;
    }

    public function balances(): BalancesResource
    {
        return $this->balances;
    }

    public static function builder(): DanipaBuilder
    {
        return new DanipaBuilder();
    }

    /** @internal */
    public static function fromHttpClient(HttpClient $http): self
    {
        return new self(
            merchants: new MerchantsResource($http),
            paymentLinks: new PaymentLinksResource($http),
            invoices: new InvoicesResource($http),
            balances: new BalancesResource($http),
        );
    }
}
