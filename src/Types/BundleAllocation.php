<?php

declare(strict_types=1);

namespace Traffical\Types;

use Traffical\Support\Json;

/**
 * Allocation in the bundle: a bucket range mapped to parameter overrides.
 */
final class BundleAllocation
{
    /**
     * @param array{0: int, 1: int} $bucketRange Inclusive [start, end] range.
     * @param array<string, mixed> $overrides Parameter overrides for this allocation.
     */
    public function __construct(
        /** Unique allocation ID. Null when the bundle omits it. */
        public readonly ?string $id,
        public readonly string $name,
        public readonly array $bucketRange,
        public readonly array $overrides,
        /** Stable key of the allocation (for warehouse data matching). */
        public readonly ?string $key = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $range = Json::arr($data, 'bucketRange');
        $start = isset($range[0]) && is_numeric($range[0]) ? (int) $range[0] : 0;
        $end = isset($range[1]) && is_numeric($range[1]) ? (int) $range[1] : 0;

        /** @var array<string, mixed> $overrides */
        $overrides = Json::arr($data, 'overrides');

        return new self(
            id: Json::strOrNull($data, 'id'),
            name: Json::str($data, 'name'),
            bucketRange: [$start, $end],
            overrides: $overrides,
            key: Json::strOrNull($data, 'key'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'id' => $this->id,
            'name' => $this->name,
            'bucketRange' => $this->bucketRange,
            'overrides' => (object) $this->overrides,
        ];
        if ($this->key !== null) {
            $out['key'] = $this->key;
        }

        return $out;
    }
}
