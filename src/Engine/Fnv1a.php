<?php

declare(strict_types=1);

namespace Traffical\Engine;

/**
 * FNV-1a (32-bit) hash.
 *
 * The canonical Traffical hash for bucket assignment. The hash is computed over
 * the UTF-8 byte sequence of the input string so that every SDK — regardless of
 * host string representation — produces identical buckets for non-ASCII keys.
 *
 * PHP strings are already byte strings, so iterating bytes hashes UTF-8 bytes
 * directly. The native `hash('fnv1a32', ...)` is used for speed and is verified
 * to match the reference byte-fold implementation in {@see self::reference()}.
 */
final class Fnv1a
{
    private const FNV_OFFSET_BASIS = 2166136261;
    private const FNV_PRIME = 16777619;
    private const MASK_32 = 0xFFFFFFFF;

    /**
     * Computes the FNV-1a hash of a string as an unsigned 32-bit integer.
     */
    public static function hash(string $input): int
    {
        /** @var string $hex */
        $hex = hash('fnv1a32', $input);

        return (int) hexdec($hex);
    }

    /**
     * Hand-rolled reference implementation. Folds each UTF-8 byte into the hash
     * using 32-bit modular multiplication. Kept for conformance cross-checking
     * against the native hash extension.
     */
    public static function reference(string $input): int
    {
        $hash = self::FNV_OFFSET_BASIS;
        $length = strlen($input);

        for ($i = 0; $i < $length; $i++) {
            $hash ^= ord($input[$i]);
            $hash = ($hash * self::FNV_PRIME) & self::MASK_32;
        }

        return $hash;
    }
}
