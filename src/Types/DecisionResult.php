<?php

declare(strict_types=1);

namespace Traffical\Types;

/**
 * Decision result with metadata for tracking.
 */
final class DecisionResult
{
    /**
     * @param array<string, mixed> $assignments Resolved parameter assignments.
     */
    public function __construct(
        public readonly string $decisionId,
        public readonly array $assignments,
        public readonly DecisionMetadata $metadata,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'decisionId' => $this->decisionId,
            'assignments' => $this->assignments,
            'metadata' => $this->metadata->toArray(),
        ];
    }
}
