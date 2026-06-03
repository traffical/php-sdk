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
    ) {
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
        );
    }
}
