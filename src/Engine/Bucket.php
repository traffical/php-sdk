<?php

declare(strict_types=1);

namespace Traffical\Engine;

use Traffical\Types\BundleAllocation;

/**
 * Deterministic bucket assignment for traffic splitting.
 *
 * bucket = fnv1a(unitKeyValue + ":" + layerId) % bucketCount
 */
final class Bucket
{
    /**
     * Computes the bucket for a given unit and layer.
     */
    public static function compute(string $unitKeyValue, string $layerId, int $bucketCount): int
    {
        $hash = Fnv1a::hash($unitKeyValue . ':' . $layerId);

        return $hash % $bucketCount;
    }

    /**
     * Checks whether a bucket falls within an inclusive range.
     *
     * @param array{0: int, 1: int} $range
     */
    public static function isInRange(int $bucket, array $range): bool
    {
        return $bucket >= $range[0] && $bucket <= $range[1];
    }

    /**
     * Finds which allocation matches a given bucket.
     *
     * @param list<BundleAllocation> $allocations
     */
    public static function findMatchingAllocation(int $bucket, array $allocations): ?BundleAllocation
    {
        foreach ($allocations as $allocation) {
            if (self::isInRange($bucket, $allocation->bucketRange)) {
                return $allocation;
            }
        }

        return null;
    }
}
