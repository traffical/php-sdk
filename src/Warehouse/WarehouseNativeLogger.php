<?php

declare(strict_types=1);

namespace Traffical\Warehouse;

use Traffical\Types\AssignmentLogEntry;

/**
 * Maps an {@see AssignmentLogEntry} to a warehouse-friendly `snake_case`
 * payload and forwards it to a custom sink. Unlike the early JS plugin, this
 * INCLUDES `policy_key` and `allocation_key` so warehouse joins can use the
 * stable keys.
 *
 * The entry's `properties` (filtered context) are spread into the top level as
 * CUPED covariates, matching the JS `createWarehouseNativeLoggerPlugin` output.
 */
final class WarehouseNativeLogger implements AssignmentLogger
{
    /** @var callable(array<string, mixed>): void */
    private $sink;

    /**
     * @param callable(array<string, mixed>): void $sink Receives the snake_case row.
     */
    public function __construct(callable $sink)
    {
        $this->sink = $sink;
    }

    public function log(AssignmentLogEntry $entry): void
    {
        ($this->sink)($this->toRow($entry));
    }

    /**
     * @return array<string, mixed>
     */
    public function toRow(AssignmentLogEntry $entry): array
    {
        $row = [
            'unit_key' => $entry->unitKey,
            'policy_id' => $entry->policyId,
            'policy_key' => $entry->policyKey,
            'allocation_name' => $entry->allocationName,
            'allocation_key' => $entry->allocationKey,
            'timestamp' => $entry->timestamp,
            'layer_id' => $entry->layerId,
            'allocation_id' => $entry->allocationId,
            'org_id' => $entry->orgId,
            'project_id' => $entry->projectId,
            'env' => $entry->env,
            'type' => $entry->type->value,
            'decision_id' => $entry->decisionId,
            'anonymous_id' => $entry->anonymousId,
            'assignment_id' => $entry->id,
            'bucket' => $entry->bucket,
            'propensity' => $entry->probability,
            'model_version' => $entry->modelVersion,
            'config_version' => $entry->configVersion,
        ];

        if ($entry->properties !== null) {
            foreach ($entry->properties as $key => $value) {
                $row[$key] = $value;
            }
        }

        return $row;
    }
}
