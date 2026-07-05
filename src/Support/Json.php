<?php

declare(strict_types=1);

namespace Traffical\Support;

/**
 * Typed accessors for decoded-JSON arrays. Centralizes the mixed -> scalar
 * narrowing at the deserialization boundary so DTO factories stay free of
 * unchecked casts.
 */
final class Json
{
    /**
     * @param array<string, mixed> $data
     */
    public static function str(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? null;
        if (is_string($value)) {
            return $value;
        }

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function strOrNull(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function int(array $data, string $key, int $default = 0): int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function intOrNull(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function float(array $data, string $key, float $default = 0.0): float
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (float) $value : $default;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function floatOrNull(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function bool(array $data, string $key, bool $default = false): bool
    {
        $value = $data[$key] ?? null;

        return is_bool($value) ? $value : $default;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int|string, mixed>
     */
    public static function arr(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : [];
    }
}
