<?php

declare(strict_types=1);

namespace Traffical;

/**
 * Options bag for {@see Client::track()} (A1).
 *
 * Keeping the optional arguments in a single value object — rather than a
 * growing list of positional trailing parameters — is what lets `values` and
 * `eventTimestamp` exist consistently across every Traffical SDK.
 */
final class TrackOptions
{
    /**
     * @param array<string, float|int>|null $values Multiple named numeric values.
     */
    public function __construct(
        /** Link this event to a prior decide() for attribution. */
        public readonly ?string $decisionId = null,
        /** Override the unit key for this event. */
        public readonly ?string $unitKey = null,
        /** Single numeric value (e.g. revenue). */
        public readonly int|float|null $value = null,
        /** Multiple named numeric values for multi-objective optimization. */
        public readonly ?array $values = null,
        /** Explicit event time (ISO 8601); defaults to "now" when omitted. */
        public readonly ?string $eventTimestamp = null,
    ) {
    }
}
