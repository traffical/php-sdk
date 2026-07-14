<?php

declare(strict_types=1);

namespace Traffical\Config;

use Traffical\Types\ConfigBundle;

/**
 * A source of config bundles: implementations decide where the bundle comes
 * from (HTTP, file, inline, cache) and the client is agnostic to the delivery
 * mechanism.
 */
interface ConfigSource
{
    /**
     * Loads the current config bundle, or null if unavailable.
     *
     * Implementations should fail soft (return null / last-known bundle) rather
     * than throwing, so flag evaluation can gracefully degrade to defaults.
     */
    public function load(): ?ConfigBundle;
}
