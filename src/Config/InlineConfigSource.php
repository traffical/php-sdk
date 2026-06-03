<?php

declare(strict_types=1);

namespace Traffical\Config;

use Traffical\Types\ConfigBundle;

/**
 * A config source backed by an in-memory bundle. Useful for offline operation,
 * tests, and bootstrapping the client with a known-good bundle.
 */
final class InlineConfigSource implements ConfigSource
{
    public function __construct(
        private readonly ConfigBundle $bundle,
    ) {
    }

    public function load(): ?ConfigBundle
    {
        return $this->bundle;
    }
}
