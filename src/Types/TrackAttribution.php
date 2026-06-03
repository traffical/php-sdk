<?php

declare(strict_types=1);

namespace Traffical\Types;

/**
 * Attribution for a track event to a specific policy/allocation.
 */
final class TrackAttribution
{
    public function __construct(
        public readonly string $layerId,
        public readonly string $policyId,
        public readonly string $allocationName,
        public readonly ?float $weight = null,
        public readonly ?string $model = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'layerId' => $this->layerId,
            'policyId' => $this->policyId,
            'allocationName' => $this->allocationName,
        ];
        if ($this->weight !== null) {
            $out['weight'] = $this->weight;
        }
        if ($this->model !== null) {
            $out['model'] = $this->model;
        }

        return $out;
    }
}
