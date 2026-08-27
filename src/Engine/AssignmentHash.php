<?php

declare(strict_types=1);

namespace Traffical\Engine;

/**
 * SHA-256 assignment hash (contract v2).
 *
 * The canonical Traffical hash for bucket assignment and weighted selection.
 * Every Traffical SDK (JS, PHP, Swift) and the edge runtime must produce
 * byte-identical results for the same inputs.
 *
 * Why SHA-256 over the previous FNV-1a: FNV-1a passed single-layer uniformity
 * but failed cross-layer independence with realistic UUID/ULID unit keys
 * and `lay_*` layer IDs (assignment in one layer could predict assignment in
 * another). SHA-256's avalanche behaviour removes that correlation.
 *
 * The hash is computed over the UTF-8 byte sequence of a length-framed,
 * domain-separated input. PHP strings are byte strings, so `strlen()` already
 * yields the UTF-8 byte length used for framing.
 */
final class AssignmentHash
{
    public const VERSION = 'v2';

    /** 2^53 — keeps full IEEE-754 double precision for the uniform value. */
    private const UNIFORM_MODULUS = 9007199254740992;

    /**
     * The v2 contract assumes a 64-bit platform: unit keys and buckets are
     * derived from the first 64 bits of the digest, and canonical numeric
     * stringification (S2) relies on 64-bit integer semantics. Fail loudly on a
     * 32-bit runtime rather than silently drift buckets.
     */
    private static function assert64Bit(): void
    {
        if (PHP_INT_SIZE < 8) {
            throw new \RuntimeException(
                'Traffical requires a 64-bit PHP build (PHP_INT_SIZE >= 8); '
                . 'the SHA-256 v2 assignment hash is not deterministic on 32-bit.',
            );
        }
    }

    /**
     * Builds the canonical, length-framed, domain-separated assignment input.
     *
     * Format:
     *   traffical:assignment:v2|u:<unitLen>:<unitKeyValue>|l:<layerLen>:<layerId>
     *
     * `<unitLen>` / `<layerLen>` are the number of UTF-8 bytes of each value.
     */
    public static function input(string $unitKeyValue, string $layerId): string
    {
        self::assert64Bit();
        $unitLen = strlen($unitKeyValue);
        $layerLen = strlen($layerId);

        return 'traffical:assignment:' . self::VERSION
            . '|u:' . $unitLen . ':' . $unitKeyValue
            . '|l:' . $layerLen . ':' . $layerId;
    }

    /**
     * Raw 32-byte SHA-256 digest of the UTF-8 bytes of the input.
     */
    public static function digest(string $input): string
    {
        return hash('sha256', $input, true);
    }

    /**
     * Reduces the first 64 bits of a digest (unsigned big-endian) modulo
     * $modulus using overflow-safe base-256 byte folding. This is exact and
     * avoids 64-bit overflow on every platform for the moduli used here
     * (bucketCount and 2^53).
     */
    public static function mod64(string $digest, int $modulus): int
    {
        $acc = 0;
        for ($i = 0; $i < 8; $i++) {
            $acc = ($acc * 256 + ord($digest[$i])) % $modulus;
        }

        return $acc;
    }

    /**
     * Uniform value in [0, 1) derived from the first 64 bits of a digest via
     * mod 2^53, matching the JS/Swift weighted-selection contract.
     */
    public static function uniform(string $digest): float
    {
        return self::mod64($digest, self::UNIFORM_MODULUS) / self::UNIFORM_MODULUS;
    }
}
