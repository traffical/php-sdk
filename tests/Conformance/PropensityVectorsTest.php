<?php

declare(strict_types=1);

namespace Traffical\Tests\Conformance;

use PHPUnit\Framework\TestCase;
use Traffical\Engine\ResolutionEngine;
use Traffical\Tests\Support\Fixtures;
use Traffical\Types\ConfigBundle;

/**
 * Propensity conformance against the canonical sdk-spec contextual vectors.
 * The events_conformance.json vector "contextual_exposure_with_propensity"
 * pins the floored-softmax probability of the low_engagement_desktop case
 * from expected_contextual.json: scores [0, 0.6, 0.4], gamma 1.0, floor 0.05
 * not binding -> P(treatment_a) = e^0.6 / (e^0 + e^0.6 + e^0.4) = 0.422379.
 */
final class PropensityVectorsTest extends TestCase
{
    public function testContextualPropensityMatchesCanonicalVector(): void
    {
        $bundle = ConfigBundle::fromArray(Fixtures::load('bundle_contextual.json'));

        $decision = ResolutionEngine::decide(
            $bundle,
            ['userId' => 'user-low-engage', 'engagement_score' => 1, 'device_type' => 'desktop'],
            ['ui.heroVariant' => 'default'],
        );

        $layer = $decision->metadata->layers[0];
        self::assertSame('policy_contextual', $layer->policyId);
        self::assertSame('treatment_a', $layer->allocationName);
        self::assertNotNull($layer->probability);
        self::assertEqualsWithDelta(0.422379, $layer->probability, 1e-6);
    }

    public function testStaticBundleVectorsEmitNoPropensity(): void
    {
        $bundle = ConfigBundle::fromArray(Fixtures::load('bundle_basic.json'));

        $decision = ResolutionEngine::decide(
            $bundle,
            ['userId' => 'user-abc'],
            ['ui.primaryColor' => '#FFFFFF', 'ui.buttonText' => 'Buy', 'pricing.discount' => 0],
        );

        $matched = 0;
        foreach ($decision->metadata->layers as $layer) {
            if ($layer->policyId !== null) {
                $matched++;
            }
            self::assertNull($layer->probability, "static layer {$layer->layerId} must omit probability");
            self::assertNull($layer->modelVersion);
        }
        self::assertGreaterThan(0, $matched, 'expected at least one matched static policy');
    }
}
