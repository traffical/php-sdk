<?php

declare(strict_types=1);

namespace Traffical\Types;

use Traffical\Support\Json;

/**
 * Categorical coefficient: score += values[contextValue], or missing when
 * absent/unknown.
 */
final class BundleCategoricalCoefficient
{
    /**
     * @param array<string, float> $values Map of category values to coefficients.
     */
    public function __construct(
        public readonly string $key,
        public readonly array $values,
        public readonly float $missing,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $values = [];
        foreach (Json::arr($data, 'values') as $k => $v) {
            $values[(string) $k] = is_numeric($v) ? (float) $v : 0.0;
        }

        return new self(
            key: Json::str($data, 'key'),
            values: $values,
            missing: Json::float($data, 'missing'),
        );
    }
}
