<?php

declare(strict_types=1);

namespace Danipa\Sdk\Tests\Support;

use Danipa\Sdk\Http\HttpClient;

/** Builds an HttpClient over a FakeClient with a fake clock and deterministic jitter. */
final class Sdk
{
    public const BASE = 'https://api.sandbox.danipa.com/ms';

    /** @param (\Closure(int): int)|null $random */
    public static function http(
        FakeClient $fake,
        ?Clock $clock = null,
        int $maxRetries = 2,
        ?\Closure $random = null,
        string $apiKey = 'dk_test_secret',
    ): HttpClient {
        return new HttpClient(
            apiKey: $apiKey,
            baseUrl: self::BASE,
            maxRetries: $maxRetries,
            client: $fake,
            sleeper: ($clock ?? new Clock())->sleeper(),
            random: $random ?? static fn (int $max): int => $max,
        );
    }
}
