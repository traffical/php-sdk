<?php

declare(strict_types=1);

namespace Traffical\Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use Traffical\Engine\WeightedSelection;

final class WeightedSelectionTest extends TestCase
{
    public function testSingleWeightAlwaysSelectsZero(): void
    {
        self::assertSame(0, WeightedSelection::select([1.0], 'any-seed'));
        self::assertSame(0, WeightedSelection::select([], 'any-seed'));
    }

    public function testDeterministicForSameSeed(): void
    {
        $weights = [0.5, 0.5];
        $first = WeightedSelection::select($weights, 'entity-1:user-abc:policy_1');
        $second = WeightedSelection::select($weights, 'entity-1:user-abc:policy_1');
        self::assertSame($first, $second);
        self::assertContains($first, [0, 1]);
    }

    public function testZeroWeightLeadingBucketIsSkipped(): void
    {
        // A leading zero-weight allocation can never be selected.
        for ($i = 0; $i < 100; $i++) {
            self::assertSame(1, WeightedSelection::select([0.0, 1.0], 'seed-' . $i));
        }
    }

    public function testRoughlyRespectsWeights(): void
    {
        $counts = [0, 0];
        for ($i = 0; $i < 2000; $i++) {
            $counts[WeightedSelection::select([0.2, 0.8], 'unit-' . $i)]++;
        }
        // Index 1 (weight 0.8) should dominate by a wide margin.
        self::assertGreaterThan($counts[0], $counts[1]);
    }
}
