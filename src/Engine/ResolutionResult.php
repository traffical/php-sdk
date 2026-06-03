<?php

declare(strict_types=1);

namespace Traffical\Engine;

use Traffical\Types\BundlePolicy;
use Traffical\Types\LayerResolution;

/**
 * Internal resolution result with metadata. Not part of the public API.
 *
 * @internal
 */
final class ResolutionResult
{
    /**
     * @param array<string, mixed> $assignments
     * @param list<LayerResolution> $layers
     * @param list<BundlePolicy> $matchedPolicies
     */
    public function __construct(
        public readonly array $assignments,
        public readonly string $unitKeyValue,
        public readonly array $layers,
        public readonly array $matchedPolicies,
    ) {
    }
}
