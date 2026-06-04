<?php

declare(strict_types=1);

namespace Traffical\Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use Traffical\Engine\AssignmentHash;
use Traffical\Engine\Bucket;

final class AssignmentHashTest extends TestCase
{
    public function testCanonicalInputFraming(): void
    {
        self::assertSame(
            'traffical:assignment:v2|u:8:user-abc|l:8:layer_ui',
            AssignmentHash::input('user-abc', 'layer_ui'),
        );
    }

    public function testFramesLengthInUtf8Bytes(): void
    {
        // The rocket emoji is 4 UTF-8 bytes; "user-🚀-42" is 12 bytes.
        self::assertSame(
            'traffical:assignment:v2|u:12:user-🚀-42|l:8:layer_ui',
            AssignmentHash::input('user-🚀-42', 'layer_ui'),
        );
    }

    public function testDigestMatchesCanonicalSha256(): void
    {
        self::assertSame(
            'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad',
            bin2hex(AssignmentHash::digest('abc')),
        );
    }

    public function testKnownAsciiBuckets(): void
    {
        self::assertSame(177, Bucket::compute('user-abc', 'layer_ui', 1000));
        self::assertSame(902, Bucket::compute('user-abc', 'layer_pricing', 1000));
        self::assertSame(443, Bucket::compute('user-xyz', 'layer_ui', 1000));
        self::assertSame(141, Bucket::compute('user-xyz', 'layer_pricing', 1000));
        self::assertSame(480, Bucket::compute('user-123', 'layer_ui', 1000));
        self::assertSame(738, Bucket::compute('user-123', 'layer_pricing', 1000));
    }

    public function testKnownNonAsciiBuckets(): void
    {
        // Non-ASCII must hash by UTF-8 bytes, matching the JS reference SDK.
        self::assertSame(693, Bucket::compute('ユーザー', 'layer_ui', 1000));
        self::assertSame(771, Bucket::compute('user-🚀-42', 'layer_ui', 1000));
    }

    public function testBucketIsAlwaysInRange(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $bucket = Bucket::compute('seed-' . $i, 'layer_test', 1000);
            self::assertGreaterThanOrEqual(0, $bucket);
            self::assertLessThan(1000, $bucket);
        }
    }

    public function testUniformIsDeterministicAndInRange(): void
    {
        $digest = AssignmentHash::digest('ctx:user-abc:policy_contextual');
        $a = AssignmentHash::uniform($digest);
        $b = AssignmentHash::uniform($digest);
        self::assertSame($a, $b);
        self::assertGreaterThanOrEqual(0.0, $a);
        self::assertLessThan(1.0, $a);
    }
}
