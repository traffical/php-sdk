<?php

declare(strict_types=1);

namespace Traffical\Types;

/**
 * Layer definition in the bundle.
 */
final class BundleLayer
{
    /**
     * @param list<BundlePolicy> $policies
     */
    public function __construct(
        public readonly string $id,
        public readonly array $policies,
        /**
         * Unit key override for this layer. When set, the SDK reads this
         * context field instead of bundle.hashing.unitKey for bucketing.
         * Null when no (valid) override is configured.
         */
        public readonly ?string $unitKey = null,
        /**
         * True when an override key WAS supplied but is empty or whitespace-only
         * (S1). Such an override is invalid: the layer is skipped (bucket -1)
         * and MUST NOT fall back to the project unit key. Kept distinct from a
         * plain absent override so resolution can tell the two apart.
         */
        public readonly bool $invalidUnitKeyOverride = false,
    ) {
    }

    /**
     * @param array{id: string, policies?: list<array<string, mixed>>, unitKey?: string} $data
     */
    public static function fromArray(array $data): self
    {
        // S1: reject an empty/whitespace-only override at parse (degrade, don't
        // throw). We null the override AND flag it invalid so the layer is
        // skipped rather than silently falling back to the project unit key.
        $rawOverride = $data['unitKey'] ?? null;
        $invalidOverride = is_string($rawOverride) && trim($rawOverride) === '';
        $unitKey = (is_string($rawOverride) && !$invalidOverride) ? $rawOverride : null;

        return new self(
            id: $data['id'],
            policies: array_map(
                static fn (array $p): BundlePolicy => BundlePolicy::fromArray($p),
                $data['policies'] ?? [],
            ),
            unitKey: $unitKey,
            invalidUnitKeyOverride: $invalidOverride,
        );
    }
}
