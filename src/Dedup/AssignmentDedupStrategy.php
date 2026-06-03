<?php

declare(strict_types=1);

namespace Traffical\Dedup;

/**
 * Strategy for deciding whether an assignment-logger key should be emitted.
 */
interface AssignmentDedupStrategy
{
    /**
     * Returns true the first time a key is seen within the dedup window and
     * records it; false for subsequent duplicates.
     */
    public function shouldEmit(string $key): bool;
}
