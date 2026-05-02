<?php

declare(strict_types=1);

namespace Danipa\Sdk\Errors;

/**
 * Thrown when the backend returns a non-2xx response. Carries the parsed
 * error envelope (matches `StatusUtils.createErrorStatus` on the fintech
 * side) so callers can branch on stable error codes.
 */
final class DanipaApiError extends DanipaError
{
    /**
     * @param int                      $status        HTTP status code (4xx or 5xx).
     * @param string                   $errorCode     Stable error code from the backend envelope, or `UNKNOWN`.
     *                                                Named `errorCode` to avoid clashing with the inherited
     *                                                `Exception::$code` (an int the SDK doesn't use).
     * @param string                   $message       Human-readable description.
     * @param string|null              $correlationId Backend correlation id for log-tracing, when present.
     * @param array<mixed>|string|null $raw           Parsed error envelope (array) or raw body (string), or null when empty.
     */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly ?string $correlationId,
        public readonly array|string|null $raw,
    ) {
        parent::__construct($message);
    }
}
