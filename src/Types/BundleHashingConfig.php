<?php

declare(strict_types=1);

namespace Traffical\Types;

/**
 * Hashing configuration in the bundle.
 */
final class BundleHashingConfig
{
    public function __construct(
        /** The context field name to use as the unit key. */
        public readonly string $unitKey,
        /** Total number of buckets for allocation. */
        public readonly int $bucketCount,
    ) {
    }

    /**
     * @param array{unitKey: string, bucketCount: int|float} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            unitKey: $data['unitKey'],
            bucketCount: (int) $data['bucketCount'],
        );
    }

    /**
     * @return array{unitKey: string, bucketCount: int}
     */
    public function toArray(): array
    {
        return ['unitKey' => $this->unitKey, 'bucketCount' => $this->bucketCount];
    }
}
