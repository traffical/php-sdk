<?php

declare(strict_types=1);

namespace Traffical\Tests\Conformance;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Traffical\Engine\Bucket;
use Traffical\Engine\EdgeResult;
use Traffical\Engine\ResolutionEngine;
use Traffical\Engine\ResolveOptions;
use Traffical\Tests\Support\Fixtures;
use Traffical\Types\ConfigBundle;

/**
 * Conformance harness: runs every bundle/expected fixture pair from the
 * language-agnostic sdk-spec and asserts buckets, assignments, and per-layer
 * resolution match the canonical reference outputs.
 */
final class SpecVectorsTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function expectedFiles(): iterable
    {
        $files = [
            'expected_basic.json',
            'expected_conditions.json',
            'expected_conditions_omitted.json',
            'expected_contextual.json',
            'expected_contextual_boundary.json',
            'expected_contextual_gamma_zero.json',
            'expected_contextual_high_floor.json',
            'expected_edge_policies.json',
            'expected_per_layer_unit_key.json',
            'expected_unicode.json',
            'expected_empty_unit_key.json',
            'expected_numeric_unit_key.json',
        ];

        foreach ($files as $file) {
            yield $file => [$file];
        }
    }

    #[DataProvider('expectedFiles')]
    public function testVectors(string $expectedFile): void
    {
        $expected = Fixtures::load($expectedFile);
        self::assertArrayHasKey('bundle', $expected, "{$expectedFile} missing 'bundle' reference");

        /** @var string $bundleName */
        $bundleName = $expected['bundle'];
        $bundle = ConfigBundle::fromArray(Fixtures::load($bundleName));

        /** @var list<array<string, mixed>> $testCases */
        $testCases = $expected['testCases'];
        self::assertNotEmpty($testCases, "{$expectedFile} has no test cases");

        foreach ($testCases as $tc) {
            $this->runCase($bundle, $tc, $expectedFile);
        }
    }

    /**
     * @param array<string, mixed> $tc
     */
    private function runCase(ConfigBundle $bundle, array $tc, string $file): void
    {
        /** @var string $name */
        $name = $tc['name'] ?? 'unnamed';
        $label = "{$file} :: {$name}";

        /** @var array<string, mixed> $context */
        $context = $tc['context'] ?? [];

        $defaults = isset($tc['defaults']) && is_array($tc['defaults'])
            ? $tc['defaults']
            : $this->defaultsFromBundle($bundle);

        $options = $this->buildOptions($tc);

        $decision = ResolutionEngine::decide($bundle, $context, $defaults, $options);

        // Assignments
        if (isset($tc['expectedAssignments']) && is_array($tc['expectedAssignments'])) {
            self::assertEquals(
                $tc['expectedAssignments'],
                $decision->assignments,
                "{$label}: assignments mismatch",
            );

            // resolveParameters must agree with decide().
            $params = ResolutionEngine::resolveParameters($bundle, $context, $defaults, $options);
            self::assertEquals(
                $tc['expectedAssignments'],
                $params,
                "{$label}: resolveParameters mismatch",
            );
        }

        // Hashing buckets
        if (isset($tc['expectedHashing']) && is_array($tc['expectedHashing'])) {
            /** @var array<string, array{bucket: int}> $expectedHashing */
            $expectedHashing = $tc['expectedHashing'];
            foreach ($expectedHashing as $layerId => $info) {
                $layer = $this->findLayer($decision->metadata->layers, $layerId);
                self::assertNotNull($layer, "{$label}: missing layer {$layerId}");
                self::assertSame(
                    (int) $info['bucket'],
                    $layer->bucket,
                    "{$label}: bucket mismatch for {$layerId}",
                );

                // Cross-check the standalone bucket helper for non -1 buckets
                // that use the project unit key.
                if ((int) $info['bucket'] >= 0 && $layer->unitKey === null) {
                    // Canonical S2 stringification (matches the engine), not a
                    // raw (string) cast — numeric unit keys must agree.
                    $rawUnit = $context[$bundle->hashing->unitKey] ?? '';
                    $unitValue = $rawUnit === '' ? '' : \Traffical\Engine\Strings::jsString($rawUnit);
                    self::assertSame(
                        (int) $info['bucket'],
                        Bucket::compute($unitValue, $layerId, $bundle->hashing->bucketCount),
                        "{$label}: Bucket::compute mismatch for {$layerId}",
                    );
                }
            }
        }

        // Per-layer resolution. Strip documentation-only keys (e.g. "comment")
        // from the fixtures so only canonical layer fields are compared.
        if (isset($tc['expectedLayers']) && is_array($tc['expectedLayers'])) {
            $actualLayers = array_map(
                static fn ($l): array => $l->toArray(),
                $decision->metadata->layers,
            );
            /** @var list<array<string, mixed>> $expectedLayers */
            $expectedLayers = $tc['expectedLayers'];
            $expectedLayers = array_map([self::class, 'canonicalLayer'], $expectedLayers);
            self::assertEquals(
                $expectedLayers,
                $actualLayers,
                "{$label}: layers mismatch",
            );
        }

        // Contextual allocation
        if (isset($tc['expectedAllocation'])) {
            $allocationNames = array_values(array_filter(array_map(
                static fn ($l): ?string => $l->allocationName,
                $decision->metadata->layers,
            )));
            self::assertContains(
                $tc['expectedAllocation'],
                $allocationNames,
                "{$label}: expected allocation {$tc['expectedAllocation']} not selected",
            );
        }
    }

    /**
     * Keeps only canonical LayerResolution keys, dropping doc-only keys.
     *
     * @param array<string, mixed> $layer
     * @return array<string, mixed>
     */
    private static function canonicalLayer(array $layer): array
    {
        $canonical = [
            'layerId', 'bucket', 'policyId', 'policyKey', 'allocationId',
            'allocationName', 'allocationKey', 'unitKey', 'unitKeyValue',
            'attributionOnly',
        ];

        return array_intersect_key($layer, array_fill_keys($canonical, true));
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultsFromBundle(ConfigBundle $bundle): array
    {
        $defaults = [];
        foreach ($bundle->parameters as $param) {
            $defaults[$param->key] = $param->default;
        }

        return $defaults;
    }

    /**
     * @param array<string, mixed> $tc
     */
    private function buildOptions(array $tc): ?ResolveOptions
    {
        if (!isset($tc['edgeResults']) || !is_array($tc['edgeResults'])) {
            return null;
        }

        /** @var list<array{policyId: string, allocationIndex: int, entityId: string}> $edgeResults */
        $edgeResults = $tc['edgeResults'];
        if (count($edgeResults) === 0) {
            return null;
        }

        $map = [];
        foreach ($edgeResults as $e) {
            $map[$e['policyId']] = new EdgeResult((int) $e['allocationIndex'], (string) $e['entityId']);
        }

        return new ResolveOptions($map);
    }

    /**
     * @param list<\Traffical\Types\LayerResolution> $layers
     */
    private function findLayer(array $layers, string $layerId): ?\Traffical\Types\LayerResolution
    {
        foreach ($layers as $layer) {
            if ($layer->layerId === $layerId) {
                return $layer;
            }
        }

        return null;
    }
}
