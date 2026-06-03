<?php

declare(strict_types=1);

namespace Traffical\Warehouse;

use Traffical\Types\AssignmentLogEntry;

/**
 * Adapts a plain `callable(AssignmentLogEntry): void` to the
 * {@see AssignmentLogger} interface so the client can accept either form.
 */
final class CallableAssignmentLogger implements AssignmentLogger
{
    /** @var callable(AssignmentLogEntry): void */
    private $callback;

    /**
     * @param callable(AssignmentLogEntry): void $callback
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function log(AssignmentLogEntry $entry): void
    {
        ($this->callback)($entry);
    }
}
