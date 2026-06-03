<?php

declare(strict_types=1);

namespace Traffical\Engine;

/**
 * Options for resolution that allow injecting pre-fetched edge results. Used by
 * server-evaluated mode where the edge worker resolves all policies (including
 * per-entity) and passes the results to the core engine.
 */
final class ResolveOptions
{
    /**
     * @param array<string, EdgeResult> $edgeResults Keyed by policyId.
     */
    public function __construct(
        public readonly array $edgeResults = [],
    ) {
    }
}
