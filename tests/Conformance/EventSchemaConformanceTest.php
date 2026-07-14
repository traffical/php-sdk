<?php

declare(strict_types=1);

namespace Traffical\Tests\Conformance;

use PHPUnit\Framework\TestCase;
use Traffical\Tests\Support\Fixtures;
use Traffical\Tests\Support\JsonSchemaValidator;
use Traffical\Types\DecisionEvent;
use Traffical\Types\ExposureEvent;
use Traffical\Types\LayerResolution;
use Traffical\Types\TrackAttribution;
use Traffical\Types\TrackEvent;

/**
 * Validates event payloads against the canonical `events.schema.json`:
 *
 *  1. Runs every case in `events_conformance.json`, asserting the SDK's view of
 *     schema-validity matches the vector's `valid` flag.
 *  2. Serializes REAL SDK-emitted decision / exposure / track payloads and
 *     asserts they validate — the guard that the PHP toArray() shapes (empty
 *     maps as `{}`, integer latencyMs, omitted null id/sdkName/sdkVersion,
 *     propensity fields) stay schema-legal.
 */
final class EventSchemaConformanceTest extends TestCase
{
    private static function validator(): JsonSchemaValidator
    {
        $schemaPath = dirname(Fixtures::dir(), 2) . '/schemas/events.schema.json';
        $contents = file_get_contents($schemaPath);
        self::assertIsString($contents, "could not read events.schema.json at {$schemaPath}");
        /** @var array<string, mixed> $schema */
        $schema = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return JsonSchemaValidator::fromArray($schema);
    }

    /**
     * Normalizes an SDK toArray() payload (which may contain stdClass for empty
     * maps) to a pure decoded-JSON array, exactly as the wire bytes decode.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function wire(array $payload): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode(json_encode($payload, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    public function testEventsConformanceVectors(): void
    {
        $validator = self::validator();
        $vectors = Fixtures::load('events_conformance.json');
        /** @var list<array<string, mixed>> $cases */
        $cases = $vectors['testCases'];
        self::assertNotEmpty($cases);

        foreach ($cases as $case) {
            /** @var string $name */
            $name = $case['name'];
            /** @var bool $expected */
            $expected = $case['valid'];
            /** @var array<string, mixed> $event */
            $event = $case['event'];

            self::assertSame(
                $expected,
                $validator->isValid($event),
                "events_conformance case '{$name}' expected valid={$this->boolStr($expected)}",
            );
        }
    }

    public function testRealDecisionEventValidatesAgainstSchema(): void
    {
        $validator = self::validator();

        $event = new DecisionEvent(
            id: 'evt_dec_1',
            orgId: 'org_test',
            projectId: 'proj_test',
            env: 'production',
            unitKey: 'user-abc',
            timestamp: '2024-06-02T10:00:00.000Z',
            assignments: ['ui.theme' => 'dark'],
            layers: [new LayerResolution(
                layerId: 'layer_ui',
                bucket: 641,
                policyId: 'policy_ui',
                allocationName: 'dark_mode',
                probability: 0.6,
            )],
            requestedParameters: ['ui.theme'],
            latencyMs: 2.7,
            configVersion: '2024-06-01T00:00:00.000Z',
            sdkName: 'php',
            sdkVersion: '0.2.0',
        );

        self::assertTrue($validator->isValid(self::wire($event->toArray())));
    }

    public function testRealExposureEventWithEmptyAssignmentsValidates(): void
    {
        $validator = self::validator();

        // Empty assignments must serialize as {} (object), not [] — otherwise the
        // schema's `assignments: object` rejects it.
        $event = new ExposureEvent(
            decisionId: 'evt_dec_1',
            orgId: 'org_test',
            projectId: 'proj_test',
            env: 'production',
            unitKey: 'user-abc',
            timestamp: '2024-06-02T10:00:00.000Z',
            assignments: [],
            layers: [new LayerResolution(layerId: 'layer_ui', bucket: 641, policyId: 'p', allocationName: 'a')],
            id: null,
            sdkName: 'php',
            sdkVersion: '0.2.0',
        );

        $wire = self::wire($event->toArray());
        self::assertIsArray($wire['assignments']);
        self::assertSame([], $wire['assignments'], 'empty assignments decode as an (empty) object, not a populated array');
        self::assertArrayNotHasKey('id', $wire, 'null id must be omitted');
        self::assertTrue($validator->isValid($wire));
    }

    public function testRealTrackEventValidatesAgainstSchema(): void
    {
        $validator = self::validator();

        $event = new TrackEvent(
            event: 'purchase',
            orgId: 'org_test',
            projectId: 'proj_test',
            env: 'production',
            unitKey: 'user-abc',
            timestamp: '2024-06-02T10:00:00.000Z',
            value: 49.0,
            properties: ['orderId' => 'o-1'],
            decisionId: 'evt_dec_1',
            values: ['revenue' => 49.0, 'items' => 3.0],
            attribution: [new TrackAttribution('layer_ui', 'policy_ui', 'dark_mode')],
            id: 'evt_trk_1',
            sdkName: 'php',
            sdkVersion: '0.2.0',
            eventTimestamp: '2024-06-02T09:59:00.000Z',
        );

        self::assertTrue($validator->isValid(self::wire($event->toArray())));
    }

    private function boolStr(bool $b): string
    {
        return $b ? 'true' : 'false';
    }
}
