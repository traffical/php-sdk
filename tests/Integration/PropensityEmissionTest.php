<?php

declare(strict_types=1);

namespace Traffical\Tests\Integration;

use Http\Mock\Client as MockHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Traffical\Client;
use Traffical\ClientOptions;
use Traffical\Config\ConfigSource;
use Traffical\Transport\EventTransport;
use Traffical\Types\ConfigBundle;
use Traffical\Types\TrackableEvent;
use Traffical\Warehouse\WarehouseNativeLogger;

/**
 * End-to-end emission of the propensity contract: layer entries on decision
 * and exposure events carry `probability`/`modelVersion`, both events carry a
 * top-level `configVersion` snapshotted at decision time, and the BYO
 * WarehouseNativeLogger row exposes them as `bucket` / `propensity` /
 * `model_version` / `config_version`.
 */
final class PropensityEmissionTest extends TestCase
{
    private const CONFIG_VERSION = '2026-07-01T00:00:00.000Z';
    private const REFRESHED_CONFIG_VERSION = '2026-07-02T00:00:00.000Z';
    private const MODEL_VERSION = '2026-06-30T12:00:00.000Z';

    public function testDecisionAndExposureEventsCarryConfigVersionAndPropensity(): void
    {
        $transport = self::recordingTransport();

        $client = new Client(new ClientOptions(
            orgId: 'org_test',
            projectId: 'prj_test',
            env: 'production',
            apiKey: 'sdk_test',
            localConfig: self::bundle(),
            eventTransport: $transport,
        ));

        $decision = $client->decide(['userId' => 'user-1'], self::defaults());
        $client->trackExposure($decision);

        /** @var list<array<string, mixed>> $events */
        $events = array_map(static fn (TrackableEvent $e): array => $e->toArray(), $transport->events);
        $byType = array_column($events, null, 'type');
        self::assertArrayHasKey('decision', $byType);
        self::assertArrayHasKey('exposure', $byType);

        foreach (['decision', 'exposure'] as $type) {
            $event = $byType[$type];
            self::assertSame(self::CONFIG_VERSION, $event['configVersion'], "{$type} event configVersion");

            /** @var array<string, array<string, mixed>> $layers */
            $layers = array_column($event['layers'], null, 'layerId');

            // Static policy: probability and modelVersion are omitted entirely.
            self::assertArrayNotHasKey('probability', $layers['layer_static']);
            self::assertArrayNotHasKey('modelVersion', $layers['layer_static']);

            // Bucket-range adaptive policy: bucket-range share, no modelVersion.
            $adaptive = $layers['layer_adaptive'];
            self::assertContains($adaptive['probability'], [0.6, 0.4]);
            self::assertArrayNotHasKey('modelVersion', $adaptive);

            // linear_contextual policy: floored-softmax probability + modelVersion.
            $ctx = $layers['layer_ctx'];
            self::assertIsFloat($ctx['probability']);
            self::assertGreaterThan(0.0, $ctx['probability']);
            self::assertLessThanOrEqual(1.0, $ctx['probability']);
            self::assertSame(self::MODEL_VERSION, $ctx['modelVersion']);
        }
    }

    public function testWarehouseNativeRowsCarryPropensityColumns(): void
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = [];
        $client = new Client(new ClientOptions(
            orgId: 'org_test',
            projectId: 'prj_test',
            env: 'production',
            apiKey: 'sdk_test',
            localConfig: self::bundle(),
            disableCloudEvents: true,
            assignmentLogger: new WarehouseNativeLogger(function (array $row) use (&$rows): void {
                $rows[] = $row;
            }),
        ));

        $decision = $client->decide(['userId' => 'user-1'], self::defaults());
        $client->trackExposure($decision);

        self::assertNotEmpty($rows);
        foreach ($rows as $row) {
            self::assertArrayHasKey('bucket', $row);
            self::assertArrayHasKey('propensity', $row);
            self::assertArrayHasKey('model_version', $row);
            self::assertArrayHasKey('config_version', $row);
            self::assertIsInt($row['bucket']);
            self::assertGreaterThanOrEqual(0, $row['bucket']);
            self::assertSame(self::CONFIG_VERSION, $row['config_version']);
        }

        $byPolicy = array_column($rows, null, 'policy_id');

        // Static policy: nullable propensity columns stay null.
        self::assertNull($byPolicy['policy_static']['propensity']);
        self::assertNull($byPolicy['policy_static']['model_version']);

        // Bucket-range adaptive policy: bucket-range share.
        self::assertContains($byPolicy['policy_bandit']['propensity'], [0.6, 0.4]);
        self::assertNull($byPolicy['policy_bandit']['model_version']);

        // linear_contextual policy: floored-softmax propensity + model version.
        self::assertIsFloat($byPolicy['policy_ctx']['propensity']);
        self::assertGreaterThan(0.0, $byPolicy['policy_ctx']['propensity']);
        self::assertSame(self::MODEL_VERSION, $byPolicy['policy_ctx']['model_version']);
    }

    public function testConfigVersionIsSnapshottedAtDecisionTime(): void
    {
        $transport = self::recordingTransport();
        /** @var list<array<string, mixed>> $rows */
        $rows = [];

        $client = new Client(new ClientOptions(
            orgId: 'org_test',
            projectId: 'prj_test',
            env: 'production',
            apiKey: 'sdk_test',
            configSource: self::sequenceSource(self::bundle(), self::bundle(self::REFRESHED_CONFIG_VERSION)),
            eventTransport: $transport,
            assignmentLogger: new WarehouseNativeLogger(function (array $row) use (&$rows): void {
                $rows[] = $row;
            }),
        ));

        $decision = $client->decide(['userId' => 'user-1'], self::defaults());
        self::assertSame(self::CONFIG_VERSION, $decision->metadata->configVersion);

        // A config refresh lands between decide() and trackExposure().
        $client->refreshConfig();
        self::assertSame(self::REFRESHED_CONFIG_VERSION, $client->getConfigVersion());

        $client->trackExposure($decision);

        /** @var list<array<string, mixed>> $events */
        $events = array_map(static fn (TrackableEvent $e): array => $e->toArray(), $transport->events);
        $byType = array_column($events, null, 'type');
        self::assertSame(self::CONFIG_VERSION, $byType['decision']['configVersion']);
        self::assertSame(
            self::CONFIG_VERSION,
            $byType['exposure']['configVersion'],
            'exposure stamps the decision-time snapshot, not the refreshed version',
        );

        self::assertNotEmpty($rows);
        foreach ($rows as $row) {
            self::assertSame(self::CONFIG_VERSION, $row['config_version']);
        }
    }

    public function testColdStartOmitsConfigVersion(): void
    {
        $client = new Client(new ClientOptions(
            orgId: 'org_test',
            projectId: 'prj_test',
            env: 'production',
            apiKey: 'sdk_test',
            configSource: self::sequenceSource(),
            disableCloudEvents: true,
        ));

        $decision = $client->decide(['userId' => 'user-1'], self::defaults());

        self::assertNull($decision->metadata->configVersion);
        self::assertArrayNotHasKey('configVersion', $decision->metadata->toArray());
    }

    public function testServerModeStampsStateVersionOfTheDecisionsResolveResponse(): void
    {
        $psr17 = new Psr17Factory();
        $mock = new MockHttpClient();
        $mock->addResponse(self::resolveResponse($psr17, 'dec_1', 'sv_1', 'user-1'));
        $mock->addResponse(self::resolveResponse($psr17, 'dec_2', 'sv_2', 'user-2'));

        $transport = self::recordingTransport();
        $client = new Client(new ClientOptions(
            orgId: 'org_test',
            projectId: 'prj_test',
            env: 'production',
            apiKey: 'sdk_test',
            evaluationMode: 'server',
            httpClient: $mock,
            requestFactory: $psr17,
            streamFactory: $psr17,
            eventTransport: $transport,
        ));

        $first = $client->decide(['userId' => 'user-1'], ['ui.color' => 'blue']);
        $second = $client->decide(['userId' => 'user-2'], ['ui.color' => 'blue']);
        self::assertSame('sv_1', $first->metadata->configVersion);
        self::assertSame('sv_2', $second->metadata->configVersion);

        // Exposure for the FIRST decision after a newer resolve has landed:
        // it stamps that decision's stateVersion, not the latest response's.
        $client->trackExposure($first);

        /** @var list<array<string, mixed>> $events */
        $events = array_map(static fn (TrackableEvent $e): array => $e->toArray(), $transport->events);
        $byType = array_column($events, null, 'type');
        self::assertSame('sv_1', $byType['exposure']['configVersion']);
        self::assertSame('dec_1', $byType['exposure']['decisionId']);
    }

    // =========================================================================
    // Fixtures
    // =========================================================================

    /**
     * @return EventTransport&object{events: list<TrackableEvent>}
     */
    private static function recordingTransport(): EventTransport
    {
        return new class () implements EventTransport {
            /** @var list<TrackableEvent> */
            public array $events = [];

            public function log(TrackableEvent $event): void
            {
                $this->events[] = $event;
            }

            public function flush(): void
            {
            }
        };
    }

    /**
     * A ConfigSource that serves the given bundles in order, then null. Lets
     * tests advance the config version across refreshConfig() calls (or start
     * cold with no bundle at all).
     */
    private static function sequenceSource(ConfigBundle ...$bundles): ConfigSource
    {
        return new class (array_values($bundles)) implements ConfigSource {
            /**
             * @param list<ConfigBundle> $bundles
             */
            public function __construct(private array $bundles)
            {
            }

            public function load(): ?ConfigBundle
            {
                return array_shift($this->bundles);
            }
        };
    }

    private static function resolveResponse(
        Psr17Factory $psr17,
        string $decisionId,
        string $stateVersion,
        string $unitKeyValue,
    ): ResponseInterface {
        $body = json_encode([
            'decisionId' => $decisionId,
            'assignments' => ['ui.color' => 'green'],
            'metadata' => [
                'timestamp' => '2026-07-01T00:00:00.000Z',
                'unitKeyValue' => $unitKeyValue,
                'layers' => [[
                    'layerId' => 'layer_static',
                    'bucket' => 1,
                    'policyId' => 'policy_static',
                    'allocationName' => 'treatment',
                ]],
            ],
            'stateVersion' => $stateVersion,
        ], JSON_THROW_ON_ERROR);

        return $psr17->createResponse(200)->withBody($psr17->createStream($body));
    }

    private static function bundle(string $version = self::CONFIG_VERSION): ConfigBundle
    {
        return ConfigBundle::fromArray([
            'version' => $version,
            'orgId' => 'org_test',
            'projectId' => 'prj_test',
            'env' => 'production',
            'hashing' => ['unitKey' => 'userId', 'bucketCount' => 1000],
            'parameters' => [
                ['key' => 'ui.color', 'type' => 'string', 'default' => 'blue', 'layerId' => 'layer_static'],
                ['key' => 'checkout.button', 'type' => 'string', 'default' => 'control', 'layerId' => 'layer_adaptive'],
                ['key' => 'hero.variant', 'type' => 'string', 'default' => 'control', 'layerId' => 'layer_ctx'],
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
                        // Canonical key emitted by the bundle builder
                        // (trainingSummary.generatedAt).
                        'generatedAt' => self::MODEL_VERSION,
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
        ];
    }
}
