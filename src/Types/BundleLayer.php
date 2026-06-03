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
         */
        public readonly ?string $unitKey = null,
    ) {
    }

    /**
     * @param array{id: string, policies?: list<array<string, mixed>>, unitKey?: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            policies: array_map(
                static fn (array $p): BundlePolicy => BundlePolicy::fromArray($p),
                $data['policies'] ?? [],
            ),
            unitKey: $data['unitKey'] ?? null,
        );
    }
}
