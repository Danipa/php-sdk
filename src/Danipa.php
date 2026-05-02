<?php

declare(strict_types=1);

namespace Danipa\Sdk;

use Danipa\Sdk\Http\HttpClient;
use Danipa\Sdk\Resource\CollectionsResource;
use Danipa\Sdk\Resource\DisbursementsResource;
use Danipa\Sdk\Resource\InvoicesResource;
use Danipa\Sdk\Resource\PaymentLinksResource;
use Danipa\Sdk\Resource\WalletsResource;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * The main entry point. Construct once with your API key and reuse the same
 * instance across requests — every resource shares the underlying HTTP
 * client (auth, retry, timeout).
 *
 * ```php
 * use Danipa\Sdk\Danipa;
 * use Danipa\Sdk\RequestOptions;
 * use Danipa\Sdk\Model\CreateCollectionRequest;
 * use Danipa\Sdk\Model\Payer;
 *
 * $danipa = Danipa::builder()
 *     ->apiKey(getenv('DANIPA_API_KEY'))
 *     ->build();
 *
 * $collection = $danipa->collections()->create(
 *     new CreateCollectionRequest(
 *         amount: '125.00',
 *         currency: 'GHS',
 *         payer: Payer::phone('+233244112233', 'Ama K.'),
 *     ),
 *     RequestOptions::withIdempotencyKey('order-1234'),
 * );
 * ```
 *
 * Sandbox vs production is inferred from the API key prefix:
 *   `dk_test_…` (or any non-`dk_live_` key) → sandbox
 *   `dk_live_…` → production
 * Override with `Builder::baseUrl()`.
 */
final class Danipa
{
    public function __construct(
        private readonly CollectionsResource $collections,
        private readonly DisbursementsResource $disbursements,
        private readonly WalletsResource $wallets,
        private readonly PaymentLinksResource $paymentLinks,
        private readonly InvoicesResource $invoices,
    ) {
    }

    public function collections(): CollectionsResource
    {
        return $this->collections;
    }

    public function disbursements(): DisbursementsResource
    {
        return $this->disbursements;
    }

    public function wallets(): WalletsResource
    {
        return $this->wallets;
    }

    public function paymentLinks(): PaymentLinksResource
    {
        return $this->paymentLinks;
    }

    public function invoices(): InvoicesResource
    {
        return $this->invoices;
    }

    public static function builder(): DanipaBuilder
    {
        return new DanipaBuilder();
    }

    /** @internal */
    public static function fromHttpClient(HttpClient $http): self
    {
        return new self(
            collections: new CollectionsResource($http),
            disbursements: new DisbursementsResource($http),
            wallets: new WalletsResource($http),
            paymentLinks: new PaymentLinksResource($http),
            invoices: new InvoicesResource($http),
        );
    }
}
