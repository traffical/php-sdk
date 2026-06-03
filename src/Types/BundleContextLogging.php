<?php

declare(strict_types=1);

namespace Traffical\Types;

/**
 * Context logging configuration: which context fields to include in exposure
 * events for contextual bandit training.
 */
final class BundleContextLogging
{
    /**
     * @param list<string> $allowedFields
     */
    public function __construct(
        public readonly array $allowedFields,
    ) {
    }

    /**
     * @param array{allowedFields?: list<string>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(allowedFields: $data['allowedFields'] ?? []);
    }
}
