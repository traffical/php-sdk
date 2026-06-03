<?php

declare(strict_types=1);

namespace Traffical\Types;

/**
 * Marker interface for all events sent to the control plane event endpoint.
 */
interface TrackableEvent
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
