<?php

declare(strict_types=1);

namespace Traffical\Engine;

/**
 * ECMAScript `String(value)` formatting for scalar context values (S2).
 *
 * Unit-key values, entity-id parts and categorical lookup keys are stringified
 * with the ECMAScript Number::toString algorithm (ECMA-262 6.1.6.1.20) so a
 * numeric key produces the SAME string — and therefore the SAME bucket — on
 * every SDK. PHP's default `(string)` cast diverges from JS at the exponent
 * boundaries (e.g. `1.0E+21` vs `1e+21`, precision-14 rounding) and would
 * silently change buckets and scores, so this reimplements the JS formatting on
 * top of the shortest round-trip digits produced by `json_encode()` (which,
 * with `serialize_precision = -1`, emits the shortest decimal that round-trips
 * the IEEE-754 double, the same digit string `repr()` yields in Python).
 *
 * This is the PHP port of `python-sdk` `engine/strings.py`; the two MUST agree
 * byte-for-byte.
 */
final class Strings
{
    private const EXP_UPPER = 21;
    private const EXP_LOWER = -6;

    /**
     * Mirrors JavaScript `String()` for the scalar context values the engine
     * reads (unit keys, entity parts, categorical features). Numeric values —
     * both ints and floats — are routed through the double-based
     * {@see numberToString()} so `9007199254740993` (an exact PHP int) and
     * `9007199254740993.0` stringify identically to the parsed JS double.
     */
    public static function jsString(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return self::numberToString((float) $value);
        }
        if (is_string($value)) {
            return $value;
        }

        return '';
    }

    /**
     * Mirrors ECMAScript Number::toString(10) for a finite or special double.
     * Guards against host crashes on non-finite inputs (S2).
     */
    public static function numberToString(float $value): string
    {
        if (is_nan($value)) {
            return 'NaN';
        }
        if ($value === INF) {
            return 'Infinity';
        }
        if ($value === -INF) {
            return '-Infinity';
        }
        if ($value === 0.0) {
            // Covers -0.0 too (=== 0.0 is true), matching JS String(-0) === "0".
            return '0';
        }

        $sign = $value < 0 ? '-' : '';
        [$digits, $n] = self::shortestDigits(abs($value));
        $k = strlen($digits);

        if ($k <= $n && $n <= self::EXP_UPPER) {
            return $sign . $digits . str_repeat('0', $n - $k);
        }
        if (0 < $n && $n <= self::EXP_UPPER) {
            return $sign . substr($digits, 0, $n) . '.' . substr($digits, $n);
        }
        if (self::EXP_LOWER < $n && $n <= 0) {
            return $sign . '0.' . str_repeat('0', -$n) . $digits;
        }

        $mantissa = $k === 1 ? $digits : $digits[0] . '.' . substr($digits, 1);
        $exponent = $n - 1;
        $expSign = $exponent >= 0 ? '+' : '-';

        return $sign . $mantissa . 'e' . $expSign . (string) abs($exponent);
    }

    /**
     * Shortest round-trip digits `d` and exponent `n` such that the value equals
     * `0.d * 10**n`. Uses `json_encode()` as the shortest-repr source, mirroring
     * Python's `repr()` in `_shortest_digits`.
     *
     * @return array{0: string, 1: int}
     */
    private static function shortestDigits(float $value): array
    {
        // With serialize_precision = -1 (PHP default >= 7.1) this is the
        // shortest decimal string that round-trips the double.
        $text = json_encode($value);
        if (!is_string($text)) {
            $text = (string) $value;
        }
        $text = strtolower($text);

        $ePos = strpos($text, 'e');
        if ($ePos !== false) {
            $mantissa = substr($text, 0, $ePos);
            $exp = (int) substr($text, $ePos + 1);
        } else {
            $mantissa = $text;
            $exp = 0;
        }

        $dotPos = strpos($mantissa, '.');
        if ($dotPos !== false) {
            $intPart = substr($mantissa, 0, $dotPos);
            $fracPart = substr($mantissa, $dotPos + 1);
        } else {
            $intPart = $mantissa;
            $fracPart = '';
        }

        $intDigits = ltrim($intPart, '0');
        if ($intDigits !== '') {
            $n = strlen($intDigits) + $exp;
            $digits = $intDigits . $fracPart;
        } else {
            $leadingZeros = strlen($fracPart) - strlen(ltrim($fracPart, '0'));
            $n = $exp - $leadingZeros;
            $digits = ltrim($fracPart, '0');
        }

        $digits = rtrim($digits, '0');
        if ($digits === '') {
            $digits = '0';
        }

        return [$digits, $n];
    }
}
