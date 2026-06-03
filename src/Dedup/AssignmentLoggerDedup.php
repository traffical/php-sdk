<?php

declare(strict_types=1);

namespace Traffical\Dedup;

/**
 * Deduplicates assignment-logger emissions on the key
 * `unitKey:policyId:allocationName:type`. In-request LRU by default (a fresh
 * instance per PHP request), with an optional PSR-16-backed variant for
 * cross-request dedup under PHP-FPM — see {@see CachedAssignmentLoggerDedup}.
 */
final class AssignmentLoggerDedup implements AssignmentDedupStrategy
{
    private const DEFAULT_MAX = 10_000;
    private const DEFAULT_TTL_MS = 3600_000;

    /** @var array<string, float> key -> expiry timestamp (ms) */
    private array $entries = [];

    public function __construct(
        private readonly int $maxEntries = self::DEFAULT_MAX,
        private readonly int $ttlMs = self::DEFAULT_TTL_MS,
    ) {
    }

    public static function key(string $unitKey, string $policyId, string $allocationName, string $type): string
    {
        return $unitKey . ':' . $policyId . ':' . $allocationName . ':' . $type;
    }

    /**
     * Returns true if this key should be emitted (first time within TTL) and
     * records it; false if it is a duplicate.
     */
    public function shouldEmit(string $key): bool
    {
        $now = microtime(true) * 1000;
        $expiry = $this->entries[$key] ?? null;
        if ($expiry !== null && $now < $expiry) {
            return false;
        }

        if (count($this->entries) >= $this->maxEntries) {
            // Evict the oldest inserted entry (insertion-ordered map = LRU-ish).
            $firstKey = array_key_first($this->entries);
            if ($firstKey !== null) {
                unset($this->entries[$firstKey]);
            }
        }

        $this->entries[$key] = $now + $this->ttlMs;

        return true;
    }
}
