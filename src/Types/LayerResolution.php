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
        /**
         * Propensity of the CHOSEN allocation at decision time, in (0, 1].
         * linear_contextual: the floored-softmax probability of the chosen
         * allocation. Other adaptive policies: the chosen allocation's
         * bucket-range share. Per-entity bundle-mode policies: the weight the
         * SDK actually used. Omitted (null) for static policies.
         */
        public readonly ?float $probability = null,
        /** Only for linear_contextual: model timestamp of the coefficients used. */
        public readonly ?string $modelVersion = null,
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
        if ($this->probability !== null) {
            $out['probability'] = $this->probability;
        }
        if ($this->modelVersion !== null) {
            $out['modelVersion'] = $this->modelVersion;
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
            probability: Json::floatOrNull($data, 'probability'),
            modelVersion: Json::strOrNull($data, 'modelVersion'),
        );
    }
}
