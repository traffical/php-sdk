<?php

declare(strict_types=1);

namespace Traffical\Types;

use Traffical\Support\Json;

/**
 * Metadata about a decision.
 */
final class DecisionMetadata
{
    /**
     * @param list<LayerResolution> $layers
     * @param array<string, mixed>|null $filteredContext Fields allowed by matched
     *        policies' contextLogging config, for contextual training.
     */
    public function __construct(
        public readonly string $timestamp,
        public readonly string $unitKeyValue,
        public readonly array $layers,
        public readonly ?array $filteredContext = null,
        /**
         * Config version the SDK evaluated against, snapshotted at decision
         * time: the bundle version (bundle mode) or the resolve response's
         * stateVersion (server mode). Decision/exposure events and assignment
         * rows are stamped from this snapshot, not the version current at
         * event time. Null on cold start (no config yet).
         */
        public readonly ?string $configVersion = null,
    ) {
    }

    /**
     * Returns a copy carrying the decision-time config version snapshot.
     */
    public function withConfigVersion(?string $configVersion): self
    {
        return new self(
            timestamp: $this->timestamp,
            unitKeyValue: $this->unitKeyValue,
            layers: $this->layers,
            filteredContext: $this->filteredContext,
            configVersion: $configVersion,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'timestamp' => $this->timestamp,
            'unitKeyValue' => $this->unitKeyValue,
            'layers' => array_map(static fn (LayerResolution $l): array => $l->toArray(), $this->layers),
        ];
        if ($this->filteredContext !== null) {
            $out['filteredContext'] = $this->filteredContext;
        }
        if ($this->configVersion !== null) {
            $out['configVersion'] = $this->configVersion;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        /** @var list<array<string, mixed>> $layers */
        $layers = $data['layers'] ?? [];

        return new self(
            timestamp: Json::str($data, 'timestamp'),
            unitKeyValue: Json::str($data, 'unitKeyValue'),
            layers: array_map(static fn (array $l): LayerResolution => LayerResolution::fromArray($l), $layers),
            filteredContext: isset($data['filteredContext']) && is_array($data['filteredContext'])
                ? $data['filteredContext']
                : null,
            configVersion: Json::strOrNull($data, 'configVersion'),
        );
    }
}
