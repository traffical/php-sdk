<?php

declare(strict_types=1);

namespace Traffical\Types;

use Traffical\Support\Json;

/**
 * Numeric coefficient: score += coef * contextValue, or missing when absent.
 */
final class BundleNumericCoefficient
{
    public function __construct(
        public readonly string $key,
        public readonly float $coef,
        public readonly float $missing,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            key: Json::str($data, 'key'),
            coef: Json::float($data, 'coef'),
            missing: Json::float($data, 'missing'),
        );
    }
}
