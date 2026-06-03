<?php

declare(strict_types=1);

namespace Traffical\Types;

/**
 * Parameter definition in the bundle.
 */
final class BundleParameter
{
    public function __construct(
        /** Parameter key (e.g. "ui.primaryColor"). */
        public readonly string $key,
        /** Value type: "string" | "number" | "boolean" | "json". */
        public readonly string $type,
        /** Default value when no policy overrides apply. */
        public readonly mixed $default,
        /** The layer this parameter belongs to. */
        public readonly string $layerId,
        /** Namespace for organizational purposes. */
        public readonly string $namespace,
    ) {
    }

    /**
     * @param array{key: string, type: string, default: mixed, layerId: string, namespace?: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            key: $data['key'],
            type: $data['type'],
            default: $data['default'],
            layerId: $data['layerId'],
            namespace: $data['namespace'] ?? '',
        );
    }

    /**
     * @return array{key: string, type: string, default: mixed, layerId: string, namespace: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type,
            'default' => $this->default,
            'layerId' => $this->layerId,
            'namespace' => $this->namespace,
        ];
    }
}
