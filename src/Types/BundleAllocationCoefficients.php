<?php

declare(strict_types=1);

namespace Traffical\Types;

use Traffical\Support\Json;

/**
 * Coefficients for a single allocation in the contextual model.
 */
final class BundleAllocationCoefficients
{
    /**
     * @param list<BundleNumericCoefficient> $numeric
     * @param list<BundleCategoricalCoefficient> $categorical
     */
    public function __construct(
        public readonly float $intercept,
        public readonly array $numeric,
        public readonly array $categorical,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $numeric = [];
        foreach (Json::arr($data, 'numeric') as $c) {
            if (is_array($c)) {
                $numeric[] = BundleNumericCoefficient::fromArray($c);
            }
        }

        $categorical = [];
        foreach (Json::arr($data, 'categorical') as $c) {
            if (is_array($c)) {
                $categorical[] = BundleCategoricalCoefficient::fromArray($c);
            }
        }

        return new self(
            intercept: Json::float($data, 'intercept'),
            numeric: $numeric,
            categorical: $categorical,
        );
    }
}
