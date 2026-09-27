<?php

declare(strict_types=1);

namespace Danipa\Sdk;

use Danipa\Sdk\Errors\ConfigurationError;

/**
 * The Danipa environment a client talks to. An API key belongs to exactly one
 * environment, named by its prefix (the server's `MerchantApiKeyService`):
 * `dk_test_…` is a sandbox key and `dk_live_…` a live key.
 */
enum Environment: string
{
    case SANDBOX = 'sandbox';
    /** Production is not live yet; only a `dk_live_` key reaches it by default. */
    case LIVE = 'live';

    public const SANDBOX_KEY_PREFIX = 'dk_test_';
    public const LIVE_KEY_PREFIX = 'dk_live_';

    public function baseUrl(): string
    {
        return match ($this) {
            self::SANDBOX => 'https://api.sandbox.danipa.com/ms',
            self::LIVE => 'https://api.danipa.com/ms',
        };
    }

    /**
     * The environment named by an API key's prefix.
     *
     * @throws ConfigurationError when the prefix is neither `dk_test_` nor `dk_live_`.
     *                            The SDK never guesses: a guess could send a key to the wrong environment.
     */
    public static function fromApiKey(string $apiKey): self
    {
        if (str_starts_with($apiKey, self::SANDBOX_KEY_PREFIX)) {
            return self::SANDBOX;
        }
        if (str_starts_with($apiKey, self::LIVE_KEY_PREFIX)) {
            return self::LIVE;
        }
        throw new ConfigurationError(
            'Danipa SDK: cannot tell the environment from the API key: it must start with "dk_test_" (sandbox) '
            . 'or "dk_live_" (live). Pass baseUrl() or environment() explicitly if you really mean to use this key.',
        );
    }
}
