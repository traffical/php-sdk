<?php

declare(strict_types=1);

namespace Traffical\Transport;

use Traffical\Types\TrackableEvent;

/**
 * Sink for analytics events (decisions, exposures, tracks) sent to the
 * Traffical control plane.
 */
interface EventTransport
{
    /**
     * Enqueues an event for delivery. Implementations may batch and flush
     * lazily.
     */
    public function log(TrackableEvent $event): void;

    /**
     * Flushes any buffered events. Must be fire-and-forget: errors are
     * swallowed/logged, never thrown, so event delivery cannot break the host
     * request.
     */
    public function flush(): void;
}
