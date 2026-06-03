<?php

declare(strict_types=1);

namespace Traffical\Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use Traffical\Engine\Bucket;
use Traffical\Engine\Fnv1a;

final class Fnv1aTest extends TestCase
{
    public function testKnownAsciiVector(): void
    {
        // Canonical spec example: fnv1a("user-abc:layer_ui") = 2740347551,
        // bucket (mod 1000) = 551.
        self::assertSame(2740347551, Fnv1a::hash('user-abc:layer_ui'));
        self::assertSame(551, Bucket::compute('user-abc', 'layer_ui', 1000));
    }

    public function testNativeAndReferenceAgree(): void
    {
        foreach (['', 'a', 'user-abc:layer_ui', 'org_1:prj_2:env', 'a' . str_repeat('z', 64)] as $input) {
            self::assertSame(
                Fnv1a::reference($input),
                Fnv1a::hash($input),
                sprintf('native and reference disagree for %s', $input),
            );
        }
    }

    public function testHashesUtf8Bytes(): void
    {
        // Non-ASCII must hash by UTF-8 bytes, not code units. The native and
        // hand-rolled byte-fold implementations must agree, locking the domain.
        $unicode = 'ユーザー-✓:layer_ui';
        self::assertSame(Fnv1a::reference($unicode), Fnv1a::hash($unicode));
    }

    public function testReturnsUnsigned32Bit(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $hash = Fnv1a::hash('seed-' . $i);
            self::assertGreaterThanOrEqual(0, $hash);
            self::assertLessThanOrEqual(0xFFFFFFFF, $hash);
        }
    }
}
