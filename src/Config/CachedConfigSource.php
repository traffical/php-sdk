<?php

declare(strict_types=1);

namespace Traffical\Config;

use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Traffical\Types\ConfigBundle;

/**
 * Wraps another {@see ConfigSource} with a PSR-16 shared cache keyed by
 * projectId:env. Under PHP-FPM this lets all worker processes share a single
 * bundle and avoid refetching on every request: the inner source is only
 * consulted when the cache entry is missing or its TTL has lapsed (a lazy
 * refresh pattern with a short shared-cache TTL).
 */
final class CachedConfigSource implements ConfigSource
{
    private readonly string $cacheKey;

    public function __construct(
        private readonly ConfigSource $inner,
        private readonly CacheInterface $cache,
        string $projectId,
        string $env,
        /** Cache TTL in seconds before the inner source is consulted again. */
        private readonly int $ttlSeconds = 30,
        string $keyPrefix = 'traffical:config:',
    ) {
        $this->cacheKey = $this->normalizeKey($keyPrefix . $projectId . ':' . $env);
    }

    public function load(): ?ConfigBundle
    {
        try {
            $cached = $this->cache->get($this->cacheKey);
            if ($cached instanceof ConfigBundle) {
                return $cached;
            }
        } catch (InvalidArgumentException) {
            // Fall through to a fresh load on a malformed key.
        }

        $bundle = $this->inner->load();
        if ($bundle !== null) {
            try {
                $this->cache->set($this->cacheKey, $bundle, $this->ttlSeconds);
            } catch (InvalidArgumentException) {
                // Ignore cache write failures; the bundle is still returned.
            }
        }

        return $bundle;
    }

    /**
     * Replaces PSR-16 reserved characters ({}()/\@:) so any projectId/env is a
     * valid cache key.
     */
    private function normalizeKey(string $key): string
    {
        return (string) preg_replace('/[{}()\/\\\\@:]/', '_', $key);
    }
}
