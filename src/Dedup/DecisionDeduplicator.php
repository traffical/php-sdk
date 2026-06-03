<?php

declare(strict_types=1);

namespace Traffical\Dedup;

/**
 * Pure decision deduplication. Tracks which unit+assignment combinations have
 * been seen to avoid sending duplicate decision events. Port of the core-ts
 * DecisionDeduplicator (in-memory, per process; under PHP-FPM this is
 * per-request unless backed by a shared store).
 */
final class DecisionDeduplicator
{
    private const DEFAULT_TTL_MS = 3600_000;
    private const DEFAULT_MAX_ENTRIES = 10_000;
    private const CLEANUP_THRESHOLD = 0.2;

    /** @var array<string, float> key -> timestamp (ms) */
    private array $seen = [];
    private float $lastCleanup;

    public function __construct(
        private readonly int $ttlMs = self::DEFAULT_TTL_MS,
        private readonly int $maxEntries = self::DEFAULT_MAX_ENTRIES,
    ) {
        $this->lastCleanup = self::nowMs();
    }

    /**
     * Generates a stable hash string for assignment values (sorted keys).
     *
     * @param array<string, mixed> $assignments
     */
    public static function hashAssignments(array $assignments): string
    {
        $keys = array_keys($assignments);
        sort($keys);

        $parts = [];
        foreach ($keys as $key) {
            $value = $assignments[$key];
            $valueStr = is_array($value)
                ? (string) json_encode($value)
                : self::scalarToString($value);
            $parts[] = $key . '=' . $valueStr;
        }

        return implode('|', $parts);
    }

    public static function createKey(string $unitKey, string $assignmentHash): string
    {
        return $unitKey . ':' . $assignmentHash;
    }

    /**
     * Returns true if this decision is new (and marks it), false if duplicate
     * within the TTL window.
     */
    public function checkAndMark(string $unitKey, string $assignmentHash): bool
    {
        $key = self::createKey($unitKey, $assignmentHash);
        $now = self::nowMs();
        $lastSeen = $this->seen[$key] ?? null;

        if ($lastSeen !== null && $now - $lastSeen < $this->ttlMs) {
            return false;
        }

        $this->seen[$key] = $now;
        $this->maybeCleanup($now);

        return true;
    }

    public function clear(): void
    {
        $this->seen = [];
    }

    public function size(): int
    {
        return count($this->seen);
    }

    private function maybeCleanup(float $now): void
    {
        $shouldCleanup = ($now - $this->lastCleanup) > $this->ttlMs * self::CLEANUP_THRESHOLD
            || count($this->seen) > $this->maxEntries;
        if (!$shouldCleanup) {
            return;
        }

        $this->lastCleanup = $now;
        foreach ($this->seen as $key => $timestamp) {
            if ($now - $timestamp >= $this->ttlMs) {
                unset($this->seen[$key]);
            }
        }

        if (count($this->seen) > $this->maxEntries) {
            asort($this->seen);
            $excess = count($this->seen) - $this->maxEntries;
            $this->seen = array_slice($this->seen, $excess, null, true);
        }
    }

    private static function scalarToString(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }

    private static function nowMs(): float
    {
        return microtime(true) * 1000;
    }
}
