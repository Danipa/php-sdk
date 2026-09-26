<?php

declare(strict_types=1);

namespace Danipa\Sdk\Errors;

/**
 * The server answered with a non-2xx status. Thrown as one of the typed
 * subclasses where the status maps to one ({@see AuthenticationError},
 * {@see PermissionError}, {@see NotFoundError}, {@see ConflictError},
 * {@see ValidationError}, {@see RateLimitError}, {@see ServerError}); any
 * other status (e.g. 402 BILLING_SUSPENDED) is thrown as this class.
 *
 * Both server error shapes are parsed: the `ApiStatus` envelope
 * (`errors[0].code/description`, `fieldErrors[]`, `retryable`,
 * `retryAfterSeconds`, `traceId`, `correlationId`, `messageKey`) and the flat
 * `{code, description}` written by the security / rate-limit / idempotency
 * filters.
 */
class DanipaApiError extends DanipaError
{
    /**
     * @param int                      $status            HTTP status code.
     * @param string                   $errorCode         Stable error code (`API_KEY_SCOPE_DENIED`, ...) or `UNKNOWN`.
     *                                                    Named `errorCode` because `Exception::$code` is an int.
     * @param string                   $message           Human-readable description.
     * @param string|null              $requestId         `X-Correlation-Id` / `X-Request-Id` response header, else the
     *                                                    body's `correlationId` / `traceId`. Quote it to support.
     * @param array<mixed>|string|null $raw               Parsed body, raw body text, or null when empty.
     * @param list<FieldError>         $fieldErrors       Field-level validation errors (envelope shape only).
     * @param string|null              $idempotencyKey    The `Idempotency-Key` sent with the request, if any.
     * @param bool                     $idempotentReplay  True when the response carried `X-Idempotent-Replay: true`.
     */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly ?string $requestId = null,
        public readonly array|string|null $raw = null,
        public readonly array $fieldErrors = [],
        public readonly ?bool $retryable = null,
        public readonly ?int $retryAfterSeconds = null,
        public readonly ?string $traceId = null,
        public readonly ?string $correlationId = null,
        public readonly ?string $messageKey = null,
        public readonly ?string $idempotencyKey = null,
        public readonly bool $idempotentReplay = false,
    ) {
        parent::__construct($message);
    }
}
