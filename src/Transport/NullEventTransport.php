<?php

declare(strict_types=1);

namespace Traffical\Transport;

use Traffical\Types\TrackableEvent;

/**
 * A no-op transport. Used when cloud events are disabled so the rest of the
 * client can call log()/flush() unconditionally.
 */
final class NullEventTransport implements EventTransport
{
    public function log(TrackableEvent $event): void
    {
    }

    public function flush(): void
    {
    }
}
