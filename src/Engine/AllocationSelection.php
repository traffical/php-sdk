<?php

declare(strict_types=1);

namespace Traffical\Engine;

use Traffical\Types\BundleAllocation;

/**
 * An allocation selected by a probabilistic resolver, together with the
 * probability (propensity) the resolver actually used for the choice.
 * Emitted on layer entries so off-policy training can weight rewards.
 */
final class AllocationSelection
{
    public function __construct(
        public readonly BundleAllocation $allocation,
        /** Selection probability of the chosen allocation, in (0, 1]. */
        public readonly float $probability,
    ) {
    }
}
