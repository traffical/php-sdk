<?php

declare(strict_types=1);

namespace Traffical\Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use Traffical\Engine\Contextual;
use Traffical\Engine\EdgeResult;
use Traffical\Engine\ResolutionEngine;
use Traffical\Engine\ResolveOptions;
use Traffical\Engine\WeightedSelection;
use Traffical\Types\BundleContextualModel;
use Traffical\Types\ConfigBundle;
use Traffical\Types\LayerResolution;

/**
 * Per-layer propensity logging: `probability` is the propensity of the CHOSEN
 * allocation at decision time (floored softmax for linear_contextual,
 * bucket-range share for other adaptive policies, the actually-used weight for
 * per-entity bundle-mode policies) and is OMITTED for static policies.
 * `modelVersion` is emitted only for linear_contextual policies.
 */
final class PropensityResolutionTest extends TestCase
{
    private const BUCKET_COUNT = 1000;
    private const MODEL_VERSION = '2026-06-30T12:00:00.000Z';
    private const STATE_VERSION = '2026-06-28T08:00:00.000Z';

    public function testStaticPolicyOmitsProbabilityAndModelVersion(): void
    {
        $decision = ResolutionEngine::decide(self::bundle(), ['userId' => 'user-1'], self::defaults());

        $layer = self::layer($decision->metadata->layers, 'layer_static');
        self::assertNotNull($layer->policyId, 'static policy should match');
        self::assertNull($layer->probability);
        self::assertNull($layer->modelVersion);
        self::assertArrayNotHasKey('probability', $layer->toArray());
        self::assertArrayNotHasKey('modelVersion', $layer->toArray());
    }

    public function testAdaptivePolicyEmitsBucketRangeShare(): void
    {
        $decision = ResolutionEngine::decide(self::bundle(), ['userId' => 'user-1'], self::defaults());

        $layer = self::layer($decision->metadata->layers, 'layer_adaptive');
        self::assertSame('policy_bandit', $layer->policyId);
        // control covers [0, 599] (share 0.6), treatment [600, 999] (share 0.4).
        $expected = $layer->allocationName === 'control' ? 600 / self::BUCKET_COUNT : 400 / self::BUCKET_COUNT;
        self::assertSame($expected, $layer->probability);
        self::assertNull($layer->modelVersion, 'modelVersion is linear_contextual-only');
    }

    public function testContextualPolicyEmitsFlooredSoftmaxProbabilityAndModelVersion(): void
    {
        $decision = ResolutionEngine::decide(self::bundle(), ['userId' => 'user-1'], self::defaults());

        $layer = self::layer($decision->metadata->layers, 'layer_ctx');
        self::assertSame('policy_ctx', $layer->policyId);
        self::assertSame(self::MODEL_VERSION, $layer->modelVersion);

        // Recompute the floored-softmax distribution over the intercept-only
        // scores [0.0, 0.6] and the deterministic selection for this unit.
        $probs = Contextual::softmaxProbabilities([0.0, 0.6], 1.0);
        $floored = Contextual::applyProbabilityFloor($probs, 0.05);
        $selectedIndex = WeightedSelection::select($floored, 'ctx:user-1:policy_ctx');

        self::assertSame($floored[$selectedIndex], $layer->probability);
        self::assertNotNull($layer->probability);
        self::assertGreaterThan(0.0, $layer->probability);
        self::assertLessThanOrEqual(1.0, $layer->probability);
    }

    public function testContextualModelVersionPrefersGeneratedAtOverAlias(): void
    {
        $model = BundleContextualModel::fromArray([
            'gamma' => 1.0,
            'actionProbabilityFloor' => 0.05,
            'defaultAllocationScore' => 0.0,
            'generatedAt' => self::MODEL_VERSION,
            'modelVersion' => '2026-06-01T00:00:00.000Z',
            'coefficients' => [],
        ]);

        self::assertSame(self::MODEL_VERSION, $model->modelVersion, 'generatedAt is canonical; modelVersion is the alias');
    }

    public function testContextualModelVersionOmittedWhenNoModelTimestamp(): void
    {
        // S7: when the contextual model carries neither generatedAt nor
        // modelVersion, the SDK MUST omit modelVersion entirely rather than
        // fall back to the policy stateVersion (which would be a wrong label).
        $bundle = self::contextualBundle(withModelTimestamp: false);
        $decision = ResolutionEngine::decide($bundle, ['userId' => 'user-1'], ['hero.variant' => 'control']);

        $layer = self::layer($decision->metadata->layers, 'layer_ctx');
        self::assertSame('policy_ctx', $layer->policyId);
        self::assertNull($layer->modelVersion, 'no generatedAt/modelVersion -> omit, no stateVersion fallback');
    }

    public function testContextualModelTimestampWinsOverPolicyStateVersion(): void
    {
        $bundle = self::contextualBundle(withModelTimestamp: true);
        $decision = ResolutionEngine::decide($bundle, ['userId' => 'user-1'], ['hero.variant' => 'control']);

        $layer = self::layer($decision->metadata->layers, 'layer_ctx');
        self::assertSame(self::MODEL_VERSION, $layer->modelVersion);
    }

    public function testEntityWeightAboveOneIsOmittedNotClamped(): void
    {
        // Malformed warehouse state: the weight actually used is > 1, which
        // violates the events schema's (0, 1]. The allocation still resolves
        // but the propensity is omitted rather than clamped.
        $decision = ResolutionEngine::decide(
            self::entityBundle([1.8, 0.2]),
            ['userId' => 'user-1', 'storeId' => 'store-1'],
            ['search.ranker' => 'a'],
        );

        $layer = self::layer($decision->metadata->layers, 'layer_entity');
        self::assertSame('policy_entity', $layer->policyId);
        self::assertNotNull($layer->allocationName);
        self::assertNull($layer->probability);
        self::assertArrayNotHasKey('probability', $layer->toArray());
    }

    public function testEntityWeightOfExactlyOneIsEmitted(): void
    {
        $decision = ResolutionEngine::decide(
            self::entityBundle([1.0, 0.0]),
            ['userId' => 'user-1', 'storeId' => 'store-1'],
            ['search.ranker' => 'a'],
        );

        $layer = self::layer($decision->metadata->layers, 'layer_entity');
        self::assertSame('policy_entity', $layer->policyId);
        self::assertSame(1.0, $layer->probability, '(0, 1] includes 1 exactly');
    }

    public function testContextualProbabilityMatchesResolvePolicyWithProbability(): void
    {
        $bundle = self::bundle();
        $policy = $bundle->layers[2]->policies[0];

        $selection = Contextual::resolvePolicyWithProbability($policy, ['userId' => 'user-1'], 'user-1');

        self::assertNotNull($selection);
        self::assertSame(
            Contextual::resolvePolicy($policy, ['userId' => 'user-1'], 'user-1'),
            $selection->allocation,
        );
        self::assertGreaterThan(0.0, $selection->probability);
        self::assertLessThanOrEqual(1.0, $selection->probability);
    }

    public function testPerEntityPolicyEmitsWeightActuallyUsed(): void
    {
        $context = ['userId' => 'user-1', 'storeId' => 'store-1'];
        $decision = ResolutionEngine::decide(self::bundle(), $context, self::defaults());

        $layer = self::layer($decision->metadata->layers, 'layer_entity');
        self::assertSame('policy_entity', $layer->policyId);

        $weights = [0.25, 0.75];
        $selectedIndex = WeightedSelection::select($weights, 'store-1:user-1:policy_entity');
        self::assertSame($weights[$selectedIndex], $layer->probability);
    }

    public function testPerEntityColdStartEmitsUniformWeight(): void
    {
        $context = ['userId' => 'user-1', 'storeId' => 'store-unknown'];
        $decision = ResolutionEngine::decide(self::bundle(), $context, self::defaults());

        $layer = self::layer($decision->metadata->layers, 'layer_entity');
        self::assertSame('policy_entity', $layer->policyId);
        self::assertSame(0.5, $layer->probability, 'unknown entity falls back to uniform weights');
    }

    public function testEdgeResolvedPolicyOmitsProbability(): void
    {
        $bundle = ConfigBundle::fromArray([
            'version' => '2026-07-01T00:00:00.000Z',
            'orgId' => 'org_test',
            'projectId' => 'prj_test',
            'env' => 'production',
            'hashing' => ['unitKey' => 'userId', 'bucketCount' => self::BUCKET_COUNT],
            'parameters' => [
                ['key' => 'edge.param', 'type' => 'string', 'default' => 'a', 'layerId' => 'layer_edge'],
            ],
            'layers' => [
                ['id' => 'layer_edge', 'policies' => [[
                    'id' => 'policy_edge',
                    'state' => 'running',
                    'kind' => 'adaptive',
                    'conditions' => [],
                    'entityConfig' => ['entityKeys' => ['storeId'], 'resolutionMode' => 'edge'],
                    'allocations' => [
                        ['id' => 'edge_a', 'name' => 'variant_a', 'bucketRange' => [0, 0], 'overrides' => ['edge.param' => 'a']],
                        ['id' => 'edge_b', 'name' => 'variant_b', 'bucketRange' => [0, 0], 'overrides' => ['edge.param' => 'b']],
                    ],
                ]]],
            ],
        ]);

        $options = new ResolveOptions(['policy_edge' => new EdgeResult(1, 'store-1')]);
        $context = ['userId' => 'user-1', 'storeId' => 'store-1'];
        $decision = ResolutionEngine::decide($bundle, $context, ['edge.param' => 'a'], $options);

        $layer = self::layer($decision->metadata->layers, 'layer_edge');
        self::assertSame('policy_edge', $layer->policyId);
        self::assertSame('variant_b', $layer->allocationName);
        self::assertNull($layer->probability, 'the SDK never saw the weight the edge used');
    }

    public function testLayerResolutionRoundTripsProbabilityAndModelVersion(): void
    {
        $layer = new LayerResolution(
            layerId: 'layer_ctx',
            bucket: 42,
            policyId: 'policy_ctx',
            allocationName: 'treatment_a',
            probability: 0.422379,
            modelVersion: self::MODEL_VERSION,
        );

        $out = $layer->toArray();
        self::assertSame(0.422379, $out['probability']);
        self::assertSame(self::MODEL_VERSION, $out['modelVersion']);

        $parsed = LayerResolution::fromArray($out);
        self::assertSame(0.422379, $parsed->probability);
        self::assertSame(self::MODEL_VERSION, $parsed->modelVersion);
    }

    // =========================================================================
    // Fixture
    // =========================================================================

    private static function bundle(): ConfigBundle
    {
        return ConfigBundle::fromArray([
            'version' => '2026-07-01T00:00:00.000Z',
            'orgId' => 'org_test',
            'projectId' => 'prj_test',
            'env' => 'production',
            'hashing' => ['unitKey' => 'userId', 'bucketCount' => self::BUCKET_COUNT],
            'parameters' => [
                ['key' => 'ui.color', 'type' => 'string', 'default' => 'blue', 'layerId' => 'layer_static'],
                ['key' => 'checkout.button', 'type' => 'string', 'default' => 'control', 'layerId' => 'layer_adaptive'],
                ['key' => 'hero.variant', 'type' => 'string', 'default' => 'control', 'layerId' => 'layer_ctx'],
                ['key' => 'search.ranker', 'type' => 'string', 'default' => 'a', 'layerId' => 'layer_entity'],
            ],
            'layers' => [
                ['id' => 'layer_static', 'policies' => [[
                    'id' => 'policy_static',
                    'state' => 'running',
                    'kind' => 'static',
                    'conditions' => [],
                    'allocations' => [
                        ['id' => 's_control', 'name' => 'control', 'bucketRange' => [0, 499], 'overrides' => ['ui.color' => 'blue']],
                        ['id' => 's_treatment', 'name' => 'treatment', 'bucketRange' => [500, 999], 'overrides' => ['ui.color' => 'green']],
                    ],
                ]]],
                ['id' => 'layer_adaptive', 'policies' => [[
                    'id' => 'policy_bandit',
                    'state' => 'running',
                    'kind' => 'adaptive',
                    // stateVersion must NOT leak into modelVersion here: the
                    // fallback applies to linear_contextual policies only.
                    'stateVersion' => self::STATE_VERSION,
                    'conditions' => [],
                    'allocations' => [
                        ['id' => 'b_control', 'name' => 'control', 'bucketRange' => [0, 599], 'overrides' => ['checkout.button' => 'control']],
                        ['id' => 'b_treatment', 'name' => 'treatment', 'bucketRange' => [600, 999], 'overrides' => ['checkout.button' => 'treatment']],
                    ],
                ]]],
                ['id' => 'layer_ctx', 'policies' => [[
                    'id' => 'policy_ctx',
                    'state' => 'running',
                    'kind' => 'adaptive',
                    'conditions' => [],
                    'contextualModel' => [
                        'gamma' => 1.0,
                        'actionProbabilityFloor' => 0.05,
                        'defaultAllocationScore' => 0.0,
                        'modelVersion' => self::MODEL_VERSION,
                        'coefficients' => [
                            'control' => ['intercept' => 0.0, 'numeric' => [], 'categorical' => []],
                            'treatment_a' => ['intercept' => 0.6, 'numeric' => [], 'categorical' => []],
                        ],
                    ],
                    'allocations' => [
                        ['id' => 'c_control', 'name' => 'control', 'bucketRange' => [0, 499], 'overrides' => ['hero.variant' => 'control']],
                        ['id' => 'c_treatment', 'name' => 'treatment_a', 'bucketRange' => [500, 999], 'overrides' => ['hero.variant' => 'bold']],
                    ],
                ]]],
                ['id' => 'layer_entity', 'policies' => [[
                    'id' => 'policy_entity',
                    'state' => 'running',
                    'kind' => 'adaptive',
                    'conditions' => [],
                    'entityConfig' => ['entityKeys' => ['storeId'], 'resolutionMode' => 'bundle'],
                    'allocations' => [
                        ['id' => 'e_a', 'name' => 'ranker_a', 'bucketRange' => [0, 0], 'overrides' => ['search.ranker' => 'a']],
                        ['id' => 'e_b', 'name' => 'ranker_b', 'bucketRange' => [0, 0], 'overrides' => ['search.ranker' => 'b']],
                    ],
                ]]],
            ],
            'entityState' => [
                'policy_entity' => [
                    'entities' => [
                        'store-1' => [
                            'entityId' => 'store-1',
                            'weights' => [0.25, 0.75],
                            'computedAt' => '2026-07-01T00:00:00.000Z',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Single linear_contextual layer whose policy carries a stateVersion; the
     * contextual model's own timestamp is optional to exercise the fallback.
     */
    private static function contextualBundle(bool $withModelTimestamp): ConfigBundle
    {
        $model = [
            'gamma' => 1.0,
            'actionProbabilityFloor' => 0.05,
            'defaultAllocationScore' => 0.0,
            'coefficients' => [
                'control' => ['intercept' => 0.0, 'numeric' => [], 'categorical' => []],
                'treatment_a' => ['intercept' => 0.6, 'numeric' => [], 'categorical' => []],
            ],
        ];
        if ($withModelTimestamp) {
            $model['generatedAt'] = self::MODEL_VERSION;
        }

        return ConfigBundle::fromArray([
            'version' => '2026-07-01T00:00:00.000Z',
            'orgId' => 'org_test',
            'projectId' => 'prj_test',
            'env' => 'production',
            'hashing' => ['unitKey' => 'userId', 'bucketCount' => self::BUCKET_COUNT],
            'parameters' => [
                ['key' => 'hero.variant', 'type' => 'string', 'default' => 'control', 'layerId' => 'layer_ctx'],
            ],
            'layers' => [
                ['id' => 'layer_ctx', 'policies' => [[
                    'id' => 'policy_ctx',
                    'state' => 'running',
                    'kind' => 'adaptive',
                    'stateVersion' => self::STATE_VERSION,
                    'conditions' => [],
                    'contextualModel' => $model,
                    'allocations' => [
                        ['id' => 'c_control', 'name' => 'control', 'bucketRange' => [0, 499], 'overrides' => ['hero.variant' => 'control']],
                        ['id' => 'c_treatment', 'name' => 'treatment_a', 'bucketRange' => [500, 999], 'overrides' => ['hero.variant' => 'bold']],
                    ],
                ]]],
            ],
        ]);
    }

    /**
     * Single per-entity (bundle-mode) layer with the given store-1 weights.
     *
     * @param list<float> $weights
     */
    private static function entityBundle(array $weights): ConfigBundle
    {
        return ConfigBundle::fromArray([
            'version' => '2026-07-01T00:00:00.000Z',
            'orgId' => 'org_test',
            'projectId' => 'prj_test',
            'env' => 'production',
            'hashing' => ['unitKey' => 'userId', 'bucketCount' => self::BUCKET_COUNT],
            'parameters' => [
                ['key' => 'search.ranker', 'type' => 'string', 'default' => 'a', 'layerId' => 'layer_entity'],
            ],
            'layers' => [
                ['id' => 'layer_entity', 'policies' => [[
                    'id' => 'policy_entity',
                    'state' => 'running',
                    'kind' => 'adaptive',
                    'conditions' => [],
                    'entityConfig' => ['entityKeys' => ['storeId'], 'resolutionMode' => 'bundle'],
                    'allocations' => [
                        ['id' => 'e_a', 'name' => 'ranker_a', 'bucketRange' => [0, 0], 'overrides' => ['search.ranker' => 'a']],
                        ['id' => 'e_b', 'name' => 'ranker_b', 'bucketRange' => [0, 0], 'overrides' => ['search.ranker' => 'b']],
                    ],
                ]]],
            ],
            'entityState' => [
                'policy_entity' => [
                    'entities' => [
                        'store-1' => [
                            'entityId' => 'store-1',
                            'weights' => $weights,
                            'computedAt' => '2026-07-01T00:00:00.000Z',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaults(): array
    {
        return [
            'ui.color' => 'blue',
            'checkout.button' => 'control',
            'hero.variant' => 'control',
            'search.ranker' => 'a',
        ];
    }

    /**
     * @param list<LayerResolution> $layers
     */
    private static function layer(array $layers, string $layerId): LayerResolution
    {
        foreach ($layers as $layer) {
            if ($layer->layerId === $layerId) {
                return $layer;
            }
        }

        self::fail("layer {$layerId} not resolved");
    }
}
