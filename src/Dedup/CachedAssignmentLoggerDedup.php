<?php

declare(strict_types=1);

namespace Traffical\Dedup;

use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * PSR-16-backed assignment dedup for cross-request deduplication under
 * PHP-FPM. Because each request gets a fresh in-memory deduper, the in-memory
 * variant only suppresses duplicates within a single request; this variant
 * shares state across requests/workers via the cache.
 */
final class CachedAssignmentLoggerDedup implements AssignmentDedupStrategy
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly int $ttlSeconds = 3600,
        private readonly string $keyPrefix = 'traffical:asn:',
    ) {
    }

    public function shouldEmit(string $key): bool
    {
        $cacheKey = $this->keyPrefix . md5($key);

        try {
            if ($this->cache->has($cacheKey)) {
                return false;
            }
            $this->cache->set($cacheKey, 1, $this->ttlSeconds);
        } catch (InvalidArgumentException) {
            // On cache errors, fail open (emit) rather than dropping data.
            return true;
        }

        return true;
    }
}
