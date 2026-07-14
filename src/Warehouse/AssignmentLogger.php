<?php

declare(strict_types=1);

namespace Traffical\Warehouse;

use Traffical\Types\AssignmentLogEntry;

/**
 * Receives structured assignment log entries for warehouse-native analytics
 * (the "bring your own pipeline" pattern). Implement this — or pass a plain
 * `callable(AssignmentLogEntry): void` to the client — to route assignments to
 * your CDP, an HTTP API, or a direct data-warehouse write.
 */
interface AssignmentLogger
{
    public function log(AssignmentLogEntry $entry): void;
}
