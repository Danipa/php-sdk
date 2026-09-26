<?php

declare(strict_types=1);

namespace Danipa\Sdk\Internal;

/**
 * Normalises a caller-supplied money value to the decimal string the SDK
 * sends on the wire. The fintech service's Jackson binding accepts a JSON
 * string for `BigDecimal`, and a string never loses precision in transit.
 *
 * @internal
 */
final class Money
{
    private function __construct()
    {
    }

    public static function normalize(string|int|float $value): string
    {
        if (\is_int($value)) {
            return (string) $value;
        }
        if (\is_float($value)) {
            if (!is_finite($value)) {
                throw new \InvalidArgumentException('Danipa SDK: money value must be a finite number');
            }
            // Shortest round-trip representation, independent of the
            // serialize_precision ini setting.
            $repr = var_export($value, true);
            return str_contains($repr, 'E') || str_contains($repr, 'e')
                ? rtrim(rtrim(sprintf('%.15F', $value), '0'), '.')
                : $repr;
        }
        $trimmed = trim($value);
        if ($trimmed === '' || !is_numeric($trimmed)) {
            throw new \InvalidArgumentException(sprintf('Danipa SDK: "%s" is not a decimal number', $value));
        }
        return $trimmed;
    }
}
