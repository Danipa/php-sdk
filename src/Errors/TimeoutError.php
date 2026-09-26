<?php

declare(strict_types=1);

namespace Danipa\Sdk\Errors;

/**
 * The transport timed out. Timeouts are configured on the PSR-18 client you
 * inject (e.g. Guzzle's `timeout` / `connect_timeout` options), not on the SDK.
 */
final class TimeoutError extends DanipaNetworkError
{
}
