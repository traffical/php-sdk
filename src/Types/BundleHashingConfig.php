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
     * @param array{unitKey?: mixed, bucketCount?: mixed} $data
     *
     * @throws MalformedBundleException when the hashing config is unusable (S8):
     *     a missing/empty unitKey or a bucketCount below 1 would make bucketing
     *     non-deterministic or divide by zero.
     */
    public static function fromArray(array $data): self
    {
        $unitKey = $data['unitKey'] ?? null;
        if (!is_string($unitKey) || trim($unitKey) === '') {
            throw new MalformedBundleException('hashing.unitKey is missing or empty');
        }

        $rawBucketCount = $data['bucketCount'] ?? null;
        if (!is_int($rawBucketCount) && !is_float($rawBucketCount)) {
            throw new MalformedBundleException('hashing.bucketCount is missing or non-numeric');
        }
        $bucketCount = (int) $rawBucketCount;
        if ($bucketCount < 1) {
            throw new MalformedBundleException("hashing.bucketCount must be >= 1, got {$bucketCount}");
        }

        return new self(
            unitKey: $unitKey,
            bucketCount: $bucketCount,
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
