<?php

declare(strict_types=1);

namespace Traffical\Types;

/**
 * Exposure event for tracking parameter exposures.
 */
final class ExposureEvent implements TrackableEvent
{
    /**
     * @param array<string, mixed> $assignments
     * @param list<LayerResolution> $layers
     * @param array<string, mixed>|null $context
     */
    public function __construct(
        public readonly string $decisionId,
        public readonly string $orgId,
        public readonly string $projectId,
        public readonly string $env,
        public readonly string $unitKey,
        public readonly string $timestamp,
        public readonly array $assignments,
        public readonly array $layers,
        public readonly ?array $context = null,
        public readonly ?string $id = null,
        public readonly ?string $sdkName = null,
        public readonly ?string $sdkVersion = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'type' => 'exposure',
            'id' => $this->id,
            'decisionId' => $this->decisionId,
            'orgId' => $this->orgId,
            'projectId' => $this->projectId,
            'env' => $this->env,
            'unitKey' => $this->unitKey,
            'timestamp' => $this->timestamp,
            'assignments' => $this->assignments,
            'layers' => array_map(static fn (LayerResolution $l): array => $l->toArray(), $this->layers),
            'sdkName' => $this->sdkName,
            'sdkVersion' => $this->sdkVersion,
        ];
        if ($this->context !== null) {
            $out['context'] = $this->context;
        }

        return $out;
    }
}
