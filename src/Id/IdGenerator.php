<?php

declare(strict_types=1);

namespace Traffical\Id;

use Psr\Clock\ClockInterface;

/**
 * Generates prefixed, time-sortable event IDs (ULID-based) mirroring the
 * core-ts prefixes: dec_ (decision), exp_ (exposure), trk_ (track), asn_
 * (assignment). ULIDs embed a millisecond timestamp so IDs sort by creation
 * time in analytics, matching the JS SDKs.
 */
final class IdGenerator
{
    private const CROCKFORD = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public function __construct(
        private readonly ?ClockInterface $clock = null,
    ) {
    }

    public function decision(): string
    {
        return 'dec_' . $this->ulid();
    }

    public function exposure(): string
    {
        return 'exp_' . $this->ulid();
    }

    public function trackEvent(): string
    {
        return 'trk_' . $this->ulid();
    }

    public function assignment(): string
    {
        return 'asn_' . $this->ulid();
    }

    /**
     * Generates a 26-character Crockford Base32 ULID.
     */
    public function ulid(): string
    {
        $timeMs = $this->nowMillis();

        // 10 characters of millisecond timestamp (48 bits).
        $timeChars = '';
        for ($i = 9; $i >= 0; $i--) {
            $mod = $timeMs % 32;
            $timeChars = self::CROCKFORD[$mod] . $timeChars;
            $timeMs = intdiv($timeMs, 32);
        }

        // 16 characters of randomness (80 bits).
        $randomChars = '';
        for ($i = 0; $i < 16; $i++) {
            $randomChars .= self::CROCKFORD[random_int(0, 31)];
        }

        return $timeChars . $randomChars;
    }

    private function nowMillis(): int
    {
        if ($this->clock !== null) {
            return (int) ($this->clock->now()->format('U') * 1000)
                + (int) $this->clock->now()->format('v');
        }

        return (int) (microtime(true) * 1000);
    }
}
