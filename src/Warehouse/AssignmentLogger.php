<?php

declare(strict_types=1);

namespace Traffical\Warehouse;

use Traffical\Types\AssignmentLogEntry;

/**
 * Receives structured assignment log entries for warehouse-native analytics
 * (the Eppo-style "bring your own pipeline" pattern). Implement this — or pass
 * a plain `callable(AssignmentLogEntry): void` to the client — to route
 * assignments to Segment, RudderStack, an HTTP API, or a direct DB write.
 */
interface AssignmentLogger
{
    public function log(AssignmentLogEntry $entry): void;
}
