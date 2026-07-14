<?php

declare(strict_types=1);

namespace Traffical\Types;

use Traffical\Support\Json;

/**
 * DecisionEvent — records that a decision was made (assignment computed).
 * Used for intent-to-treat analysis, debugging, and audit trail.
 */
final class DecisionEvent implements TrackableEvent
{
    /**
     * @param list<string>|null $requestedParameters
     * @param array<string, mixed> $assignments
     * @param list<LayerResolution> $layers
     * @param array<string, mixed>|null $context
     */
    public function __construct(
        public readonly string $id,
        public readonly string $orgId,
        public readonly string $projectId,
        public readonly string $env,
        public readonly string $unitKey,
        public readonly string $timestamp,
        public readonly array $assignments,
        public readonly array $layers,
        public readonly ?array $requestedParameters = null,
        public readonly ?float $latencyMs = null,
        public readonly ?array $context = null,
        public readonly ?string $sdkName = null,
        public readonly ?string $sdkVersion = null,
        /** Config bundle version the SDK evaluated against. */
        public readonly ?string $configVersion = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'type' => 'decision',
            'orgId' => $this->orgId,
            'projectId' => $this->projectId,
            'env' => $this->env,
            'unitKey' => $this->unitKey,
            'timestamp' => $this->timestamp,
            'assignments' => Json::map($this->assignments),
            'layers' => array_map(static fn (LayerResolution $l): array => $l->toArray(), $this->layers),
        ];
        // Conditionally emit optional string fields so a null never serializes
        // as a schema-invalid `"id": null`.
        if ($this->id !== null) {
            $out['id'] = $this->id;
        }
        if ($this->sdkName !== null) {
            $out['sdkName'] = $this->sdkName;
        }
        if ($this->sdkVersion !== null) {
            $out['sdkVersion'] = $this->sdkVersion;
        }
        if ($this->requestedParameters !== null) {
            $out['requestedParameters'] = $this->requestedParameters;
        }
        if ($this->latencyMs !== null) {
            // Schema types latencyMs as an integer; round the millisecond float.
            $out['latencyMs'] = (int) round($this->latencyMs);
        }
        if ($this->context !== null) {
            $out['context'] = Json::map($this->context);
        }
        if ($this->configVersion !== null) {
            $out['configVersion'] = $this->configVersion;
        }

        return $out;
    }
}
