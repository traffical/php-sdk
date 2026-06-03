<?php

declare(strict_types=1);

namespace Traffical\Types;

use Traffical\Support\Json;

/**
 * Entity weights for per-entity resolution.
 */
final class EntityWeights
{
    /**
     * @param list<float> $weights Selection weights per allocation (sum to 1.0).
     */
    public function __construct(
        public readonly string $entityId,
        public readonly array $weights,
        public readonly string $computedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $weights = [];
        foreach (Json::arr($data, 'weights') as $w) {
            $weights[] = is_numeric($w) ? (float) $w : 0.0;
        }

        return new self(
            entityId: Json::str($data, 'entityId'),
            weights: $weights,
            computedAt: Json::str($data, 'computedAt'),
        );
    }
}
