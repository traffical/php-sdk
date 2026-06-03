<?php

declare(strict_types=1);

namespace Traffical\Types;

use Traffical\Support\Json;

/**
 * Policy definition in the bundle.
 */
final class BundlePolicy
{
    /**
     * @param list<BundleAllocation> $allocations
     * @param list<BundleCondition> $conditions
     * @param array{start: int, end: int}|null $eligibleBucketRange
     */
    public function __construct(
        public readonly string $id,
        /** "draft" | "running" | "paused" | "completed". */
        public readonly string $state,
        /** "static" | "adaptive". */
        public readonly string $kind,
        public readonly array $allocations,
        public readonly array $conditions,
        public readonly ?string $stateVersion = null,
        public readonly ?BundleContextLogging $contextLogging = null,
        public readonly ?BundleContextualModel $contextualModel = null,
        public readonly ?EntityConfig $entityConfig = null,
        public readonly ?array $eligibleBucketRange = null,
        /** Stable key of the policy (for warehouse data matching). */
        public readonly ?string $key = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        /** @var list<array<string, mixed>> $allocations */
        $allocations = $data['allocations'] ?? [];
        /** @var list<array{field: string, op: string, value?: mixed, values?: list<mixed>}> $conditions */
        $conditions = $data['conditions'] ?? [];

        $eligible = null;
        if (isset($data['eligibleBucketRange']) && is_array($data['eligibleBucketRange'])) {
            /** @var array{start: int|float, end: int|float} $ebr */
            $ebr = $data['eligibleBucketRange'];
            $eligible = ['start' => (int) $ebr['start'], 'end' => (int) $ebr['end']];
        }

        return new self(
            id: Json::str($data, 'id'),
            state: Json::str($data, 'state', 'draft'),
            kind: Json::str($data, 'kind', 'static'),
            allocations: array_map(
                static fn (array $a): BundleAllocation => BundleAllocation::fromArray($a),
                $allocations,
            ),
            conditions: array_map(
                static fn (array $c): BundleCondition => BundleCondition::fromArray($c),
                $conditions,
            ),
            stateVersion: Json::strOrNull($data, 'stateVersion'),
            contextLogging: isset($data['contextLogging']) && is_array($data['contextLogging'])
                ? BundleContextLogging::fromArray($data['contextLogging'])
                : null,
            contextualModel: isset($data['contextualModel']) && is_array($data['contextualModel'])
                ? BundleContextualModel::fromArray($data['contextualModel'])
                : null,
            entityConfig: isset($data['entityConfig']) && is_array($data['entityConfig'])
                ? EntityConfig::fromArray($data['entityConfig'])
                : null,
            eligibleBucketRange: $eligible,
            key: Json::strOrNull($data, 'key'),
        );
    }
}
