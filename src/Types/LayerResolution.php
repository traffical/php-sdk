<?php

declare(strict_types=1);

namespace Traffical\Types;

use Traffical\Support\Json;

/**
 * Layer resolution info for tracking. Mirrors the conditional-spread shape of
 * the core-ts LayerResolution: only set fields appear in serialized output.
 */
final class LayerResolution
{
    public function __construct(
        public readonly string $layerId,
        /** The bucket computed for this layer (-1 when the unit key is missing). */
        public readonly int $bucket,
        public readonly ?string $policyId = null,
        public readonly ?string $policyKey = null,
        public readonly ?string $allocationId = null,
        public readonly ?string $allocationName = null,
        public readonly ?string $allocationKey = null,
        /** Present only when the layer overrides the project default unit key. */
        public readonly ?string $unitKey = null,
        public readonly ?string $unitKeyValue = null,
        /** True when resolved for attribution only (no requested parameters). */
        public readonly ?bool $attributionOnly = null,
    ) {
    }

    /**
     * Serializes to an array that omits unset optional keys, matching the
     * core-ts conditional-spread output exactly.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = ['layerId' => $this->layerId, 'bucket' => $this->bucket];
        if ($this->policyId !== null) {
            $out['policyId'] = $this->policyId;
        }
        if ($this->policyKey !== null) {
            $out['policyKey'] = $this->policyKey;
        }
        if ($this->allocationId !== null) {
            $out['allocationId'] = $this->allocationId;
        }
        if ($this->allocationName !== null) {
            $out['allocationName'] = $this->allocationName;
        }
        if ($this->allocationKey !== null) {
            $out['allocationKey'] = $this->allocationKey;
        }
        if ($this->unitKey !== null) {
            $out['unitKey'] = $this->unitKey;
        }
        if ($this->unitKeyValue !== null) {
            $out['unitKeyValue'] = $this->unitKeyValue;
        }
        if ($this->attributionOnly !== null) {
            $out['attributionOnly'] = $this->attributionOnly;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            layerId: Json::str($data, 'layerId'),
            bucket: Json::int($data, 'bucket'),
            policyId: Json::strOrNull($data, 'policyId'),
            policyKey: Json::strOrNull($data, 'policyKey'),
            allocationId: Json::strOrNull($data, 'allocationId'),
            allocationName: Json::strOrNull($data, 'allocationName'),
            allocationKey: Json::strOrNull($data, 'allocationKey'),
            unitKey: Json::strOrNull($data, 'unitKey'),
            unitKeyValue: Json::strOrNull($data, 'unitKeyValue'),
            attributionOnly: isset($data['attributionOnly']) ? Json::bool($data, 'attributionOnly') : null,
        );
    }
}
