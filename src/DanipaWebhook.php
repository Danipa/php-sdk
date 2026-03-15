<?php

declare(strict_types=1);

namespace Danipa\Sdk;

/**
 * Danipa webhook signature verification.
 *
 * Verifies that incoming webhook payloads are authentic by checking the
 * HMAC-SHA256 signature in the `X-Danipa-Signature` header.
 *
 * Usage:
 * ```php
 * use Danipa\Sdk\DanipaWebhook;
 *
 * $payload   = file_get_contents('php://input');
 * $signature = $_SERVER['HTTP_X_DANIPA_SIGNATURE'] ?? '';
 * $timestamp = $_SERVER['HTTP_X_DANIPA_TIMESTAMP'] ?? '';
 * $secret    = getenv('DANIPA_WEBHOOK_SECRET');
 *
 * if (!DanipaWebhook::verify($payload, $signature, $timestamp, $secret)) {
 *     http_response_code(401);
 *     echo json_encode(['error' => 'Invalid signature']);
 *     exit;
 * }
 *
 * $event = json_decode($payload, true);
 * // Process the webhook event...
 * http_response_code(200);
 * ```
 */
final class DanipaWebhook
{
    /** Maximum allowed age for a webhook timestamp (5 minutes). */
    public const REPLAY_WINDOW_SECONDS = 300;

    private function __construct() {}

    /**
     * Verify a webhook signature.
     *
     * @param string $payload   The raw JSON request body
     * @param string $signature The X-Danipa-Signature header (e.g., sha256=abcdef...)
     * @param string $timestamp The X-Danipa-Timestamp header (epoch seconds)
     * @param string $secret    The endpoint signing secret (starts with whsec_)
     * @return bool True if the signature is valid and the timestamp is within the replay window
     */
    public static function verify(
        string $payload,
        string $signature,
        string $timestamp,
        string $secret
    ): bool {
        if (empty($payload) || empty($signature) || empty($timestamp) || empty($secret)) {
            return false;
        }

        // Check replay window
        if (!ctype_digit($timestamp)) {
            return false;
        }

        $ts = (int) $timestamp;
        $now = time();

        if (abs($now - $ts) > self::REPLAY_WINDOW_SECONDS) {
            return false;
        }

        // Compute expected signature
        $expected = self::computeSignature($payload, $secret, $ts);

        // Constant-time comparison
        return hash_equals($expected, $signature);
    }

    /**
     * Compute the expected signature for a payload.
     *
     * @param string $payload            The raw JSON payload
     * @param string $secret             The signing secret
     * @param int    $timestampEpochSecs The timestamp in epoch seconds
     * @return string The signature in format sha256=<hex>
     */
    public static function computeSignature(
        string $payload,
        string $secret,
        int $timestampEpochSecs
    ): string {
        $signedContent = $timestampEpochSecs . '.' . $payload;
        $hash = hash_hmac('sha256', $signedContent, $secret);
        return 'sha256=' . $hash;
    }
}
