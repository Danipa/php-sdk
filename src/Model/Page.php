<?php

declare(strict_types=1);

namespace Danipa\Sdk\Model;

use Danipa\Sdk\Internal\Json;
use Danipa\Sdk\ResponseMeta;

/**
 * One page of a list endpoint (a Spring Data `Page` on the server). Pages are
 * zero-based. Reads both the direct shape (`{content, totalElements, number, ...}`)
 * and the `{content, page: {...}}` shape newer Spring Data versions write.
 *
 * @template T
 */
final class Page
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public readonly array $items,
        /** Zero-based index of this page. */
        public readonly int $number,
        /** Requested page size. */
        public readonly int $size,
        public readonly int $totalElements,
        public readonly int $totalPages,
        public readonly ?ResponseMeta $meta = null,
    ) {
    }

    public function hasNext(): bool
    {
        return $this->number + 1 < $this->totalPages;
    }

    /**
     * @template U
     * @param array<array-key, mixed>                $data
     * @param callable(array<array-key, mixed>): U $itemFactory
     * @return self<U>
     * @throws \Danipa\Sdk\Internal\MalformedResponse
     * @internal
     */
    public static function fromArray(array $data, callable $itemFactory, ?ResponseMeta $meta = null): self
    {
        $items = array_map($itemFactory, Json::objectList($data, 'content'));
        $pageInfo = isset($data['page']) && \is_array($data['page']) ? $data['page'] : $data;
        $count = \count($items);
        return new self(
            items: $items,
            number: Json::optInt($pageInfo, 'number') ?? 0,
            size: Json::optInt($pageInfo, 'size') ?? $count,
            totalElements: Json::optInt($pageInfo, 'totalElements') ?? $count,
            totalPages: Json::optInt($pageInfo, 'totalPages') ?? ($count > 0 ? 1 : 0),
            meta: $meta,
        );
    }
}
