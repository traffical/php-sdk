<?php

declare(strict_types=1);

namespace Traffical\Types;

use Traffical\Support\Json;

/**
 * Configuration for per-entity adaptive policies.
 */
final class EntityConfig
{
    /**
     * @param list<string> $entityKeys Context keys identifying the entity.
     * @param array{countKey: string}|null $dynamicAllocations
     */
    public function __construct(
        public readonly array $entityKeys,
        /** "bundle" (resolve locally) or "edge" (call edge API). */
        public readonly string $resolutionMode,
        public readonly ?int $edgeTimeoutMs = null,
        public readonly ?array $dynamicAllocations = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $entityKeys = [];
        foreach (Json::arr($data, 'entityKeys') as $key) {
            if (is_string($key)) {
                $entityKeys[] = $key;
            }
        }

        $dynamicAllocations = null;
        $rawDynamic = $data['dynamicAllocations'] ?? null;
        if (is_array($rawDynamic) && isset($rawDynamic['countKey']) && is_string($rawDynamic['countKey'])) {
            $dynamicAllocations = ['countKey' => $rawDynamic['countKey']];
        }

        return new self(
            entityKeys: $entityKeys,
            resolutionMode: Json::str($data, 'resolutionMode'),
            edgeTimeoutMs: Json::intOrNull($data, 'edgeTimeoutMs'),
            dynamicAllocations: $dynamicAllocations,
        );
    }
}
