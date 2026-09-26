<?php

declare(strict_types=1);

namespace Danipa\Sdk;

use Danipa\Sdk\Errors\WebhookVerificationError;
use Danipa\Sdk\Internal\Json;
use Danipa\Sdk\Internal\MalformedResponse;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Danipa webhook verification.
 *
 * Every delivery carries `X-Danipa-Signature: sha256=<hex>`, where the hex is
 * HMAC-SHA256(secret, "<X-Danipa-Timestamp>.<raw body>"), plus
 * `X-Danipa-Event` and `X-Danipa-Delivery`. Always verify the RAW body bytes —
 * re-encoding decoded JSON changes the bytes and breaks the signature.
 *
 * ```php
 * use Danipa\Sdk\DanipaWebhook;
 * use Danipa\Sdk\Errors\WebhookVerificationError;
 *
 * try {
 *     $event = DanipaWebhook::constructEvent(
 *         file_get_contents('php://input'),
 *         getallheaders(),
 *         [getenv('DANIPA_WEBHOOK_SECRET'), getenv('DANIPA_WEBHOOK_SECRET_PREVIOUS')],
 *     );
 * } catch (WebhookVerificationError $e) {
 *     http_response_code(400);
 *     exit;
 * }
 * // $event->type, $event->deliveryId, $event->data
 * ```
 */
final class DanipaWebhook
{
    /** Default maximum age (and future skew) of a webhook timestamp: 5 minutes. */
    public const DEFAULT_TOLERANCE_SECONDS = 300;
    /** @deprecated Use {@see self::DEFAULT_TOLERANCE_SECONDS}. */
    public const REPLAY_WINDOW_SECONDS = self::DEFAULT_TOLERANCE_SECONDS;

    public const HEADER_SIGNATURE = 'X-Danipa-Signature';
    public const HEADER_TIMESTAMP = 'X-Danipa-Timestamp';
    public const HEADER_EVENT = 'X-Danipa-Event';
    public const HEADER_DELIVERY = 'X-Danipa-Delivery';

    private function __construct()
    {
    }

    /**
     * Verify a webhook signature. Returns false (never throws) on any mismatch.
     *
     * @param string|StreamInterface      $payload          The raw request body (bytes).
     * @param string                      $signature        The `X-Danipa-Signature` header (`sha256=<hex>`).
     * @param string                      $timestamp        The `X-Danipa-Timestamp` header (epoch seconds).
     * @param string|list<string>         $secrets          Signing secret(s). Pass the new AND old secret while
     *                                                      rotating; a match against any of them is accepted.
     * @param int                         $toleranceSeconds Maximum allowed |now − timestamp|. Default 300.
     * @param int|null                    $now              Current epoch seconds (tests); defaults to time().
     */
    public static function verify(
        string|StreamInterface $payload,
        string $signature,
        string $timestamp,
        string|array $secrets,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS,
        ?int $now = null,
    ): bool {
        if ($toleranceSeconds < 0) {
            throw new \InvalidArgumentException('Danipa SDK: toleranceSeconds must be >= 0');
        }
        $body = self::bytes($payload);
        if ($body === '' || $signature === '' || $timestamp === '') {
            return false;
        }
        if (!ctype_digit($timestamp) || \strlen($timestamp) > 12) {
            return false;
        }
        $ts = (int) $timestamp;
        if (abs(($now ?? time()) - $ts) > $toleranceSeconds) {
            return false;
        }
        $matched = false;
        foreach (self::secretList($secrets) as $secret) {
            // Compare against every secret (no early exit) so timing does not
            // reveal which one matched.
            if (hash_equals(self::computeSignature($body, $secret, $ts), $signature)) {
                $matched = true;
            }
        }
        return $matched;
    }

    /**
     * Verify a delivery and return it as a typed {@see WebhookEvent}.
     *
     * @param string|StreamInterface                                  $payload The raw request body (bytes).
     * @param MessageInterface|array<string, string|list<string>|null> $headers A PSR-7 request, or a header map —
     *        `getallheaders()`, Symfony's `$request->headers->all()`, Laravel's `$request->headers->all()`, or
     *        `$_SERVER` (`HTTP_X_DANIPA_SIGNATURE` style). Header names are case-insensitive.
     * @param string|list<string> $secrets Signing secret(s); several are allowed during rotation.
     * @throws WebhookVerificationError when a header is missing, the signature or timestamp is invalid,
     *         or the body is not a JSON object.
     */
    public static function constructEvent(
        string|StreamInterface $payload,
        MessageInterface|array $headers,
        string|array $secrets,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS,
        ?int $now = null,
    ): WebhookEvent {
        $body = self::bytes($payload);
        $map = self::headerMap($headers);
        $signature = self::requireHeader($map, self::HEADER_SIGNATURE);
        $timestamp = self::requireHeader($map, self::HEADER_TIMESTAMP);

        if (!self::verify($body, $signature, $timestamp, $secrets, $toleranceSeconds, $now)) {
            throw new WebhookVerificationError(
                'Danipa webhook: signature or timestamp verification failed',
            );
        }

        $type = self::requireHeader($map, self::HEADER_EVENT);
        $deliveryId = self::requireHeader($map, self::HEADER_DELIVERY);
        try {
            $data = Json::decodeObject($body);
        } catch (MalformedResponse $e) {
            throw new WebhookVerificationError('Danipa webhook: body is not a JSON object', 0, $e);
        }
        return new WebhookEvent($type, $deliveryId, (int) $timestamp, $data, $body);
    }

    /**
     * Compute the signature for a payload: `sha256=` + lowercase hex
     * HMAC-SHA256(secret, "<timestamp>.<payload>").
     */
    public static function computeSignature(
        string|StreamInterface $payload,
        string $secret,
        int $timestampEpochSecs,
    ): string {
        return 'sha256=' . hash_hmac('sha256', $timestampEpochSecs . '.' . self::bytes($payload), $secret);
    }

    private static function bytes(string|StreamInterface $payload): string
    {
        if (\is_string($payload)) {
            return $payload;
        }
        if ($payload->isSeekable()) {
            $payload->rewind();
        }
        return $payload->getContents();
    }

    /**
     * @param string|array<array-key, mixed> $secrets
     * @return list<string>
     */
    private static function secretList(string|array $secrets): array
    {
        $out = [];
        foreach (\is_string($secrets) ? [$secrets] : $secrets as $secret) {
            if (\is_string($secret) && $secret !== '') {
                $out[] = $secret;
            }
        }
        return $out;
    }

    /**
     * @param MessageInterface|array<array-key, mixed> $headers
     * @return array<string, string>
     */
    private static function headerMap(MessageInterface|array $headers): array
    {
        if ($headers instanceof MessageInterface) {
            $headers = $headers->getHeaders();
        }
        $out = [];
        foreach ($headers as $name => $value) {
            $key = strtolower(str_replace('_', '-', (string) $name));
            if (str_starts_with($key, 'http-')) {
                $key = substr($key, 5);
            }
            if (\is_array($value)) {
                // First value of a multi-valued header; an empty list has none.
                $first = array_key_first($value);
                $value = $first === null ? null : $value[$first];
            }
            if (\is_string($value)) {
                $out[$key] = trim($value);
            }
        }
        return $out;
    }

    /** @param array<string, string> $map */
    private static function requireHeader(array $map, string $name): string
    {
        $value = $map[strtolower($name)] ?? '';
        if ($value === '') {
            throw new WebhookVerificationError(sprintf('Danipa webhook: missing %s header', $name));
        }
        return $value;
    }
}
