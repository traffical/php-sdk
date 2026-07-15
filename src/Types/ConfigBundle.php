<?php

declare(strict_types=1);

namespace Traffical\Types;

use JsonException;
use Traffical\Support\Json;

/**
 * ConfigBundle — the complete configuration for a project/environment.
 * This is what the SDK fetches and caches.
 */
final class ConfigBundle
{
    /**
     * @param list<BundleParameter> $parameters
     * @param list<BundleLayer> $layers
     * @param array<string, BundleEntityPolicyState>|null $entityState
     */
    public function __construct(
        /** ISO timestamp for cache invalidation / ETag generation. */
        public readonly string $version,
        public readonly string $orgId,
        public readonly string $projectId,
        public readonly string $env,
        public readonly BundleHashingConfig $hashing,
        public readonly array $parameters,
        public readonly array $layers,
        public readonly ?array $entityState = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws MalformedBundleException when the bundle is structurally unusable
     *     (S8) — e.g. a missing `hashing` block, empty unitKey, or bucketCount
     *     below 1. Callers catch this and fail open to a good bundle/defaults.
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['hashing']) || !is_array($data['hashing'])) {
            throw new MalformedBundleException('bundle is missing the hashing config');
        }
        /** @var array{unitKey?: mixed, bucketCount?: mixed} $hashing */
        $hashing = $data['hashing'];
        /** @var list<array{key: string, type: string, default: mixed, layerId: string, namespace?: string}> $parameters */
        $parameters = $data['parameters'] ?? [];
        /** @var list<array{id: string, policies?: list<array<string, mixed>>, unitKey?: string}> $layers */
        $layers = $data['layers'] ?? [];

        $entityState = null;
        if (isset($data['entityState']) && is_array($data['entityState'])) {
            $entityState = [];
            foreach ($data['entityState'] as $policyId => $state) {
                /** @var array{_global?: array<string, mixed>, entities?: array<string, array<string, mixed>>} $state */
                $entityState[(string) $policyId] = BundleEntityPolicyState::fromArray($state);
            }
        }

        return new self(
            version: Json::str($data, 'version'),
            orgId: Json::str($data, 'orgId'),
            projectId: Json::str($data, 'projectId'),
            env: Json::str($data, 'env'),
            hashing: BundleHashingConfig::fromArray($hashing),
            parameters: array_map(
                static fn (array $p): BundleParameter => BundleParameter::fromArray($p),
                $parameters,
            ),
            layers: array_map(
                static fn (array $l): BundleLayer => BundleLayer::fromArray($l),
                $layers,
            ),
            entityState: $entityState,
        );
    }

    /**
     * @throws JsonException when the payload is not valid JSON
     * @throws MalformedBundleException when the bundle is structurally unusable (S8)
     */
    public static function fromJson(string $json): self
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return self::fromArray($data);
    }
}
