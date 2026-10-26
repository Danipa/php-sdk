<?php

declare(strict_types=1);

namespace Danipa\Sdk\Internal;

/**
 * JSON decoding and typed field access for response bodies.
 *
 * The fintech service writes `BigDecimal` money as a JSON *number* (`125.00`).
 * `json_decode()` would turn that into a float and lose both the trailing
 * zeros and, past ~15 significant digits, the value itself. {@see decode()}
 * therefore quotes every non-integer number literal before decoding, so money
 * reaches the models as the exact decimal string the server wrote. Integer
 * literals (`quantity`, `paymentCount`) stay integers.
 *
 * Every accessor throws {@see MalformedResponse} on a missing or mistyped
 * field, so a bad body becomes a typed SDK error, never a PHP warning or a
 * `TypeError`.
 *
 * @internal
 */
final class Json
{
    private function __construct()
    {
    }

    /**
     * Decode a JSON document whose root must be an object.
     *
     * @return array<string, mixed>
     * @throws MalformedResponse
     */
    public static function decodeObject(string $json): array
    {
        $decoded = self::decode($json);
        if (!\is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            throw new MalformedResponse('expected a JSON object at the root of the response body');
        }
        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @throws MalformedResponse
     */
    public static function decode(string $json): mixed
    {
        try {
            return json_decode(self::quoteDecimals($json), true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException $e) {
            throw new MalformedResponse('response body is not valid JSON: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Wrap every non-integer JSON number literal that sits outside a string in
     * double quotes. Invalid JSON is passed through untouched (json_decode then
     * reports it).
     */
    private static function quoteDecimals(string $json): string
    {
        $out = '';
        $len = \strlen($json);
        $inString = false;
        for ($i = 0; $i < $len; $i++) {
            $c = $json[$i];
            if ($inString) {
                $out .= $c;
                if ($c === '\\' && $i + 1 < $len) {
                    $out .= $json[++$i];
                } elseif ($c === '"') {
                    $inString = false;
                }
                continue;
            }
            if ($c === '"') {
                $inString = true;
                $out .= $c;
                continue;
            }
            if ($c === '-' || ($c >= '0' && $c <= '9')) {
                if (preg_match('/\G-?(?:0|[1-9][0-9]*)(\.[0-9]+)?([eE][+-]?[0-9]+)?/', $json, $m, 0, $i) === 1) {
                    $literal = $m[0];
                    $isDecimal = ($m[1] ?? '') !== '' || ($m[2] ?? '') !== '';
                    $out .= $isDecimal ? '"' . $literal . '"' : $literal;
                    $i += \strlen($literal) - 1;
                    continue;
                }
            }
            $out .= $c;
        }
        return $out;
    }

    /**
     * @param array<array-key, mixed> $data
     * @throws MalformedResponse
     */
    public static function string(array $data, string $key): string
    {
        $v = $data[$key] ?? null;
        if (!\is_string($v)) {
            throw new MalformedResponse(sprintf('field "%s" must be a string', $key));
        }
        return $v;
    }

    /**
     * @param array<array-key, mixed> $data
     * @throws MalformedResponse
     */
    public static function optString(array $data, string $key): ?string
    {
        $v = $data[$key] ?? null;
        if ($v === null) {
            return null;
        }
        if (\is_string($v)) {
            return $v;
        }
        if (\is_int($v)) {
            return (string) $v;
        }
        throw new MalformedResponse(sprintf('field "%s" must be a string', $key));
    }

    /**
     * A money / decimal field. Accepts a JSON number or a JSON string and
     * returns the decimal as a string.
     *
     * @param array<array-key, mixed> $data
     * @throws MalformedResponse
     */
    public static function optDecimal(array $data, string $key): ?string
    {
        $v = $data[$key] ?? null;
        if ($v === null) {
            return null;
        }
        return self::toDecimal($v, $key);
    }

    /**
     * @param array<array-key, mixed> $data
     * @throws MalformedResponse
     */
    public static function decimal(array $data, string $key): string
    {
        $v = self::optDecimal($data, $key);
        if ($v === null) {
            throw new MalformedResponse(sprintf('field "%s" is required', $key));
        }
        return $v;
    }

    /**
     * @throws MalformedResponse
     */
    private static function toDecimal(mixed $v, string $key): string
    {
        if (\is_int($v)) {
            return (string) $v;
        }
        if (\is_float($v)) {
            // Only reachable for callers that bypass decode(); keep the
            // shortest round-trip representation.
            return Money::normalize($v);
        }
        if (\is_string($v) && is_numeric($v)) {
            return $v;
        }
        throw new MalformedResponse(sprintf('field "%s" must be a decimal number', $key));
    }

    /**
     * @param array<array-key, mixed> $data
     * @throws MalformedResponse
     */
    public static function optInt(array $data, string $key): ?int
    {
        $v = $data[$key] ?? null;
        if ($v === null) {
            return null;
        }
        if (!\is_int($v)) {
            throw new MalformedResponse(sprintf('field "%s" must be an integer', $key));
        }
        return $v;
    }

    /**
     * @param array<array-key, mixed> $data
     * @throws MalformedResponse
     */
    public static function int(array $data, string $key): int
    {
        $v = self::optInt($data, $key);
        if ($v === null) {
            throw new MalformedResponse(sprintf('field "%s" is required', $key));
        }
        return $v;
    }

    /**
     * @param array<array-key, mixed> $data
     * @throws MalformedResponse
     */
    public static function optBool(array $data, string $key): ?bool
    {
        $v = $data[$key] ?? null;
        if ($v === null) {
            return null;
        }
        if (!\is_bool($v)) {
            throw new MalformedResponse(sprintf('field "%s" must be a boolean', $key));
        }
        return $v;
    }

    /**
     * @param array<array-key, mixed> $data
     * @throws MalformedResponse
     */
    public static function bool(array $data, string $key, bool $default): bool
    {
        $v = $data[$key] ?? null;
        if ($v === null) {
            return $default;
        }
        if (!\is_bool($v)) {
            throw new MalformedResponse(sprintf('field "%s" must be a boolean', $key));
        }
        return $v;
    }

    /**
     * A list of JSON objects (absent or null → empty list).
     *
     * @param array<array-key, mixed> $data
     * @return list<array<array-key, mixed>>
     * @throws MalformedResponse
     */
    public static function objectList(array $data, string $key): array
    {
        $v = $data[$key] ?? null;
        if ($v === null) {
            return [];
        }
        if (!\is_array($v) || !array_is_list($v)) {
            throw new MalformedResponse(sprintf('field "%s" must be an array', $key));
        }
        $out = [];
        foreach ($v as $i => $item) {
            if (!\is_array($item)) {
                throw new MalformedResponse(sprintf('field "%s[%d]" must be an object', $key, $i));
            }
            $out[] = $item;
        }
        return $out;
    }
}
