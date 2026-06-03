<?php

declare(strict_types=1);

namespace Traffical\Types;

use Traffical\Support\Json;

/**
 * Per-entity state for a single policy in the bundle.
 */
final class BundleEntityPolicyState
{
    /**
     * @param array<string, EntityWeights> $entities Per-entity learned weights.
     */
    public function __construct(
        /** Global prior weights (used for cold start). */
        public readonly ?EntityWeights $global,
        public readonly array $entities,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $entities = [];
        foreach (Json::arr($data, 'entities') as $id => $weights) {
            if (is_array($weights)) {
                $entities[(string) $id] = EntityWeights::fromArray($weights);
            }
        }

        $rawGlobal = $data['_global'] ?? null;

        return new self(
            global: is_array($rawGlobal) ? EntityWeights::fromArray($rawGlobal) : null,
            entities: $entities,
        );
    }
}
