<?php

declare(strict_types=1);

namespace Danipa\Sdk\Errors;

/**
 * Base class for every error this SDK throws. Catch this once, then narrow:
 *
 * - {@see DanipaApiError} and its subclasses — the server answered with a
 *   non-2xx status (401, 403, 404, 409, 400/422, 429, 5xx).
 * - {@see DanipaNetworkError} ({@see ConnectionError}, {@see TimeoutError}) —
 *   no response was received.
 * - {@see ResponseParseError} — a 2xx arrived but its body could not be read.
 *   The call may have SUCCEEDED; retry only with the same idempotency key.
 * - {@see WebhookVerificationError} — a webhook failed verification.
 */
abstract class DanipaError extends \RuntimeException
{
}
