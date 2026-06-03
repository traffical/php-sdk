<?php

declare(strict_types=1);

namespace Traffical\Engine;

/**
 * A pre-fetched per-entity edge decision, injected into resolution for
 * server-evaluated / edge-mode policies.
 */
final class EdgeResult
{
    public function __construct(
        public readonly int $allocationIndex,
        public readonly string $entityId,
    ) {
    }
}
