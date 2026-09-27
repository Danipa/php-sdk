<?php

declare(strict_types=1);

namespace Danipa\Sdk\Internal;

/**
 * Path-id checks. The server's API-key route table only matches a canonical
 * UUID segment, so any other id would come back as a confusing
 * `403 API_KEY_SCOPE_DENIED`; reject it before anything is sent instead.
 *
 * @internal
 */
final class Ids
{
    private const UUID = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/D';

    private function __construct()
    {
    }

    public static function uuid(string $id, string $what): string
    {
        if (preg_match(self::UUID, $id) !== 1) {
            throw new \InvalidArgumentException(sprintf('Danipa SDK: %s must be a UUID, got "%s"', $what, $id));
        }
        return $id;
    }

    /**
     * @return array{page: int, size: int}
     */
    public static function paging(int $page, int $size): array
    {
        if ($page < 0) {
            throw new \InvalidArgumentException('Danipa SDK: page must be >= 0 (pages are zero-based)');
        }
        if ($size < 1 || $size > 200) {
            throw new \InvalidArgumentException('Danipa SDK: size must be between 1 and 200');
        }
        return ['page' => $page, 'size' => $size];
    }
}
