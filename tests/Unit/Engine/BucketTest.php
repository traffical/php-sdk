<?php

declare(strict_types=1);

namespace Traffical\Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use Traffical\Engine\Bucket;
use Traffical\Types\BundleAllocation;

final class BucketTest extends TestCase
{
    public function testComputeIsDeterministic(): void
    {
        self::assertSame(
            Bucket::compute('user-abc', 'layer_ui', 1000),
            Bucket::compute('user-abc', 'layer_ui', 1000),
        );
        self::assertGreaterThanOrEqual(0, Bucket::compute('user-abc', 'layer_ui', 1000));
        self::assertLessThan(1000, Bucket::compute('user-abc', 'layer_ui', 1000));
    }

    public function testIsInRangeIsInclusive(): void
    {
        self::assertTrue(Bucket::isInRange(0, [0, 499]));
        self::assertTrue(Bucket::isInRange(499, [0, 499]));
        self::assertFalse(Bucket::isInRange(500, [0, 499]));
    }

    public function testFindMatchingAllocation(): void
    {
        $control = new BundleAllocation('a_control', 'control', [0, 499], []);
        $treatment = new BundleAllocation('a_treatment', 'treatment', [500, 999], []);
        $allocations = [$control, $treatment];

        self::assertSame($control, Bucket::findMatchingAllocation(0, $allocations));
        self::assertSame($treatment, Bucket::findMatchingAllocation(750, $allocations));
        self::assertNull(Bucket::findMatchingAllocation(1500, $allocations));
    }
}
