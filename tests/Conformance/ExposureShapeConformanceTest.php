<?php

declare(strict_types=1);

namespace Traffical\Tests\Conformance;

use PHPUnit\Framework\TestCase;
use Traffical\Client;
use Traffical\ClientOptions;
use Traffical\Tests\Support\Fixtures;
use Traffical\Tests\Support\JsonSchemaValidator;
use Traffical\Transport\EventTransport;
use Traffical\Types\ConfigBundle;
use Traffical\Types\DecisionMetadata;
use Traffical\Types\DecisionResult;
use Traffical\Types\ExposureEvent;
use Traffical\Types\LayerResolution;
use Traffical\Types\TrackableEvent;

/**
 * S4: drives {@see Client::trackExposure()} against `exposure_shape.json` and
 * asserts the emitted exposure events match `expectedEvents`: exactly ONE event
 * carrying only newly-exposed, non-attributionOnly layers (with propensity rows
 * intact) and narrowed assignments, or ZERO events when nothing survives. Also
 * validates every emitted event against `events.schema.json`.
 */
final class ExposureShapeConformanceTest extends TestCase
{
    public function testExposureShapeVectors(): void
    {
        $schemaPath = dirname(Fixtures::dir(), 2) . '/schemas/events.schema.json';
        /** @var array<string, mixed> $schemaJson */
        $schemaJson = json_decode((string) file_get_contents($schemaPath), true, 512, JSON_THROW_ON_ERROR);
        $validator = JsonSchemaValidator::fromArray($schemaJson);

        $vectors = Fixtures::load('exposure_shape.json');
        /** @var list<array<string, mixed>> $cases */
        $cases = $vectors['testCases'];
        self::assertNotEmpty($cases);

        foreach ($cases as $case) {
            $this->runCase($case, $validator);
        }
    }

    /**
     * @param array<string, mixed> $case
     */
    private function runCase(array $case, JsonSchemaValidator $validator): void
    {
        /** @var string $name */
        $name = $case['name'];
        /** @var string $unitKey */
        $unitKey = $case['unitKey'];
        /** @var array<string, mixed> $assignments */
        $assignments = $case['assignments'];
        /** @var list<array<string, mixed>> $resolvedLayers */
        $resolvedLayers = $case['resolvedLayers'];
        /** @var string $configVersion */
        $configVersion = $case['configVersion'];
        /** @var list<array<string, mixed>> $alreadyExposed */
        $alreadyExposed = $case['alreadyExposed'];
        /** @var list<array<string, mixed>> $expectedEvents */
        $expectedEvents = $case['expectedEvents'];

        $capture = new CapturingTransport();
        $client = new Client(new ClientOptions(
            orgId: 'org_test',
            projectId: 'proj_test',
            env: 'production',
            apiKey: 'sdk_test',
            localConfig: $this->bundleFor($resolvedLayers, $assignments),
            eventTransport: $capture,
        ));

        // Prime the session dedup with the "already exposed" rows.
        if ($alreadyExposed !== []) {
            $primeLayers = [];
            foreach ($alreadyExposed as $seen) {
                $primeLayers[] = new LayerResolution(
                    layerId: (string) $seen['layerId'],
                    bucket: 0,
                    policyId: 'prime',
                    allocationName: (string) $seen['allocationName'],
                );
            }
            $client->trackExposure($this->decision($unitKey, [], $primeLayers, $configVersion));
            $capture->events = [];
        }

        $decision = $this->decision(
            $unitKey,
            $assignments,
            array_map(static fn (array $l): LayerResolution => LayerResolution::fromArray($l), $resolvedLayers),
            $configVersion,
        );
        $client->trackExposure($decision);

        $emitted = array_values(array_filter(
            $capture->events,
            static fn (TrackableEvent $e): bool => $e instanceof ExposureEvent,
        ));

        self::assertCount(count($expectedEvents), $emitted, "{$name}: exposure event count");

        foreach ($expectedEvents as $i => $expected) {
            /** @var array<string, mixed> $actual */
            $actual = $emitted[$i]->toArray();
            /** @var array<string, mixed> $wire */
            $wire = json_decode((string) json_encode($actual), true, 512, JSON_THROW_ON_ERROR);

            self::assertTrue($validator->isValid($wire), "{$name}: emitted event #{$i} must be schema-valid");
            self::assertSame($expected['unitKey'], $wire['unitKey'], "{$name}: unitKey");
            self::assertSame($expected['configVersion'], $wire['configVersion'], "{$name}: configVersion");
            self::assertEquals($expected['assignments'], $wire['assignments'], "{$name}: narrowed assignments");
            self::assertEquals(
                $this->canonicalLayers($expected['layers']),
                $wire['layers'],
                "{$name}: exposed layers",
            );
        }
    }

    /**
     * The fixture's expected layers are already in canonical LayerResolution
     * shape; this is an identity pass kept explicit for intent.
     *
     * @param list<array<string, mixed>> $layers
     * @return list<array<string, mixed>>
     */
    private function canonicalLayers(array $layers): array
    {
        return $layers;
    }

    /**
     * Builds a minimal bundle mapping the fixture's assignment parameters to
     * their owning layers, so assignment-narrowing (S4) has a param->layer map.
     *
     * @param list<array<string, mixed>> $resolvedLayers
     * @param array<string, mixed> $assignments
     */
    private function bundleFor(array $resolvedLayers, array $assignments): ConfigBundle
    {
        // The fixture uses a stable mapping: ui.* -> layer_hero,
        // checkout.* -> layer_checkout, pricing.* -> layer_pricing.
        $prefixToLayer = [
            'ui.' => 'layer_hero',
            'checkout.' => 'layer_checkout',
            'pricing.' => 'layer_pricing',
        ];

        $parameters = [];
        foreach (array_keys($assignments) as $key) {
            $layerId = 'layer_unknown';
            foreach ($prefixToLayer as $prefix => $candidate) {
                if (str_starts_with((string) $key, $prefix)) {
                    $layerId = $candidate;
                    break;
                }
            }
            $parameters[] = [
                'key' => $key,
                'type' => 'string',
                'default' => '',
                'layerId' => $layerId,
                'namespace' => explode('.', (string) $key)[0],
            ];
        }

        return ConfigBundle::fromArray([
            'version' => '2024-06-01T00:00:00.000Z',
            'orgId' => 'org_test',
            'projectId' => 'proj_test',
            'env' => 'production',
            'hashing' => ['unitKey' => 'userId', 'bucketCount' => 1000],
            'parameters' => $parameters,
            'layers' => [],
        ]);
    }

    /**
     * @param array<string, mixed> $assignments
     * @param list<LayerResolution> $layers
     */
    private function decision(string $unitKey, array $assignments, array $layers, string $configVersion): DecisionResult
    {
        return new DecisionResult(
            decisionId: 'dec_' . $unitKey,
            assignments: $assignments,
            metadata: new DecisionMetadata(
                timestamp: '2024-06-02T12:00:00.000Z',
                unitKeyValue: $unitKey,
                layers: $layers,
                filteredContext: null,
                configVersion: $configVersion,
            ),
        );
    }
}

/**
 * In-memory transport that records every logged event for assertion.
 */
final class CapturingTransport implements EventTransport
{
    /** @var list<TrackableEvent> */
    public array $events = [];

    public bool $flushed = false;

    public function log(TrackableEvent $event): void
    {
        $this->events[] = $event;
    }

    public function flush(): void
    {
        $this->flushed = true;
    }
}
