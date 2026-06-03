<?php

declare(strict_types=1);

namespace Traffical\Types;

/**
 * Structured assignment log entry for warehouse-native analytics.
 *
 * Emitted by the optional assignment logger. Contains exactly the fields
 * needed for an AssignmentDefinition SQL source in the warehouse-native
 * pipeline. Customers route these to their own pipeline (Segment,
 * Rudderstack, direct DB writes) -> warehouse table -> AssignmentDefinition.
 */
final class AssignmentLogEntry
{
    /**
     * @param array<string, mixed>|null $properties Context/properties for
     *        segmentation (CUPED covariates).
     */
    public function __construct(
        /** The unit key / entity identifier (e.g. user_id). */
        public readonly string $unitKey,
        /** The policy (experiment) identifier. */
        public readonly string $policyId,
        /** The allocation (variant) name. */
        public readonly string $allocationName,
        /** ISO 8601 timestamp of the assignment. */
        public readonly string $timestamp,
        /** The layer this assignment came from. */
        public readonly string $layerId,
        public readonly string $orgId,
        public readonly string $projectId,
        public readonly string $env,
        /** Event type that produced this row. */
        public readonly AssignmentType $type,
        /** Stable key of the policy (for warehouse data matching). */
        public readonly ?string $policyKey = null,
        /** Stable key of the allocation (for warehouse data matching). */
        public readonly ?string $allocationKey = null,
        public readonly ?string $allocationId = null,
        public readonly ?string $sdkName = null,
        public readonly ?string $sdkVersion = null,
        public readonly ?array $properties = null,
        /** Decision that produced this assignment (decision.decisionId). */
        public readonly ?string $decisionId = null,
        /** Anonymous/stable id when available (client SDKs); null on server. */
        public readonly ?string $anonymousId = null,
        /** Unique id for this assignment log entry (asn_…). */
        public readonly ?string $id = null,
    ) {
    }

    /**
     * Serializes to an array preserving the camelCase field names of the
     * canonical AssignmentLogEntry. Optional fields are always present (null
     * when unset) to mirror the JS object shape.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'unitKey' => $this->unitKey,
            'policyId' => $this->policyId,
            'policyKey' => $this->policyKey,
            'allocationName' => $this->allocationName,
            'allocationKey' => $this->allocationKey,
            'timestamp' => $this->timestamp,
            'layerId' => $this->layerId,
            'allocationId' => $this->allocationId,
            'orgId' => $this->orgId,
            'projectId' => $this->projectId,
            'env' => $this->env,
            'sdkName' => $this->sdkName,
            'sdkVersion' => $this->sdkVersion,
            'properties' => $this->properties,
            'type' => $this->type->value,
            'decisionId' => $this->decisionId,
            'anonymousId' => $this->anonymousId,
            'id' => $this->id,
        ];
    }
}
