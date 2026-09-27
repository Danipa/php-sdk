<?php

declare(strict_types=1);

namespace Danipa\Sdk\Model;

use Danipa\Sdk\Internal\Json;
use Danipa\Sdk\ResponseMeta;

/**
 * Outcome of `DELETE /v1/merchants/me/payment-links/{id}`. A link that has
 * never taken a payment is deleted (`204`); one with transactions is kept for
 * the record and deactivated instead (`200 {"action":"deactivated"}`).
 */
final class PaymentLinkDeleteResult
{
    public function __construct(
        /** True when the link was deleted outright. */
        public readonly bool $deleted,
        /** True when the link had payments and was deactivated instead. */
        public readonly bool $deactivated,
        public readonly ?string $message = null,
        public readonly ?ResponseMeta $meta = null,
    ) {
    }

    /** @internal */
    public static function deleted(?ResponseMeta $meta = null): self
    {
        return new self(deleted: true, deactivated: false, message: null, meta: $meta);
    }

    /**
     * @param array<array-key, mixed> $data
     * @throws \Danipa\Sdk\Internal\MalformedResponse
     * @internal
     */
    public static function fromArray(array $data, ?ResponseMeta $meta = null): self
    {
        $action = Json::optString($data, 'action');
        return new self(
            deleted: $action === 'deleted',
            deactivated: $action === 'deactivated',
            message: Json::optString($data, 'message'),
            meta: $meta,
        );
    }
}
