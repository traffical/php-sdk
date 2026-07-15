<?php

declare(strict_types=1);

namespace Traffical\Dedup;

/**
 * Session deduplication for cloud exposure events (S4).
 *
 * Suppresses a `(unitKey, layerId, allocationName)` combination that was already
 * exposed within the current session. PHP is a request-model runtime, so this
 * is a bounded in-memory LRU with a TTL (never an unbounded map) — long-lived
 * workers (Swoole/RoadRunner/queue consumers) cannot leak memory. SDK-side
 * dedup is a volume optimization only; the ingestion pipeline remains
 * authoritative for exact counting.
 */
final class ExposureDedup
{
    private const DEFAULT_MAX = 10_000;

    /** @var array<string, float> key -> expiry timestamp (ms) */
    private array $entries = [];

    public function __construct(
        /** Session window before a (unit, layer, allocation) may re-expose. */
        private readonly int $ttlMs = 1_800_000,
        private readonly int $maxEntries = self::DEFAULT_MAX,
    ) {
    }

    public static function key(string $unitKey, string $layerId, string $allocationName): string
    {
        return $unitKey . ':' . $layerId . ':' . $allocationName;
    }

    /**
     * Returns true if this (unit, layer, allocation) is newly exposed within the
     * session (and records it); false if it was already exposed and should be
     * suppressed.
     */
    public function shouldEmit(string $key): bool
    {
        $now = microtime(true) * 1000;
        $expiry = $this->entries[$key] ?? null;
        if ($expiry !== null && $now < $expiry) {
            return false;
        }

        if (count($this->entries) >= $this->maxEntries) {
            $firstKey = array_key_first($this->entries);
            if ($firstKey !== null) {
                unset($this->entries[$firstKey]);
            }
        }

        $this->entries[$key] = $now + $this->ttlMs;

        return true;
    }

    /**
     * Clears the session (e.g. when the unit changes). Kept for parity with the
     * client-SDK `identify()` reset semantics.
     */
    public function reset(): void
    {
        $this->entries = [];
    }
}
