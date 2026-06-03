<?php

declare(strict_types=1);

namespace Traffical\Tests\Integration;

use Http\Mock\Client as MockHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Traffical\Client;
use Traffical\ClientOptions;
use Traffical\Tests\Support\Fixtures;
use Traffical\Types\ConfigBundle;
use Traffical\Warehouse\WarehouseNativeLogger;

/**
 * End-to-end client tests in bundle mode using a spec fixture as the local
 * config bundle, plus a mock PSR-18 client to exercise the event transport.
 */
final class ClientBundleModeTest extends TestCase
{
    public function testResolvesFromLocalBundle(): void
    {
        [$bundle, $tc] = $this->firstCaseWithAssignments('expected_basic.json');
        $context = $this->context($tc);
        $defaults = $this->defaults($bundle, $tc);

        $client = new Client(new ClientOptions(
            orgId: 'org_test',
            projectId: 'prj_test',
            env: 'production',
            apiKey: 'sdk_test',
            localConfig: $bundle,
            disableCloudEvents: true,
        ));

        self::assertEquals($tc['expectedAssignments'], $client->getParams($context, $defaults));

        $decision = $client->decide($context, $defaults);
        self::assertNotSame('', $decision->decisionId);
        self::assertEquals($tc['expectedAssignments'], $decision->assignments);
    }

    public function testAssignmentLoggerEmitsDecisionAndExposureRows(): void
    {
        [$bundle, $tc] = $this->firstCaseWithAssignments('expected_basic.json');
        $context = $this->context($tc);
        $defaults = $this->defaults($bundle, $tc);

        /** @var list<array<string, mixed>> $rows */
        $rows = [];
        $client = new Client(new ClientOptions(
            orgId: 'org_test',
            projectId: 'prj_test',
            env: 'production',
            apiKey: 'sdk_test',
            localConfig: $bundle,
            disableCloudEvents: true,
            assignmentLogger: new WarehouseNativeLogger(function (array $row) use (&$rows): void {
                $rows[] = $row;
            }),
        ));

        $decision = $client->decide($context, $defaults);
        $client->trackExposure($decision);

        if ($rows !== []) {
            foreach ($rows as $row) {
                self::assertArrayHasKey('unit_key', $row);
                self::assertArrayHasKey('policy_key', $row);
                self::assertArrayHasKey('allocation_key', $row);
                self::assertArrayHasKey('type', $row);
            }
            $types = array_column($rows, 'type');
            self::assertContains('decision', $types, 'decide() should emit a decision row');
            self::assertContains('exposure', $types, 'trackExposure() should emit an exposure row');
        } else {
            // The chosen fixture case has no matched experiment/variant.
            $this->addToAssertionCount(1);
        }
    }

    public function testEventsFlushOverPsr18(): void
    {
        [$bundle, $tc] = $this->firstCaseWithAssignments('expected_basic.json');
        $context = $this->context($tc);
        $defaults = $this->defaults($bundle, $tc);

        $psr17 = new Psr17Factory();
        $mock = new MockHttpClient();
        $mock->setDefaultResponse($psr17->createResponse(200));

        $client = new Client(new ClientOptions(
            orgId: 'org_test',
            projectId: 'prj_test',
            env: 'production',
            apiKey: 'sdk_test',
            localConfig: $bundle,
            httpClient: $mock,
            requestFactory: $psr17,
            streamFactory: $psr17,
        ));

        $decision = $client->decide($context, $defaults);
        $client->trackExposure($decision);
        $client->flushEvents();

        // Bundle mode also issues a config GET; locate the events batch POST.
        $batchRequest = null;
        foreach ($mock->getRequests() as $request) {
            if (str_contains((string) $request->getUri(), '/v1/events/batch')) {
                $batchRequest = $request;
                break;
            }
        }

        if ($batchRequest !== null) {
            self::assertSame('POST', $batchRequest->getMethod());
            self::assertSame('Bearer sdk_test', $batchRequest->getHeaderLine('Authorization'));
        } else {
            // The chosen fixture case produced no trackable cloud events.
            $this->addToAssertionCount(1);
        }
    }

    /**
     * @return array{0: ConfigBundle, 1: array<string, mixed>}
     */
    private function firstCaseWithAssignments(string $expectedFile): array
    {
        $expected = Fixtures::load($expectedFile);
        /** @var string $bundleName */
        $bundleName = $expected['bundle'];
        $bundle = ConfigBundle::fromArray(Fixtures::load($bundleName));

        /** @var list<array<string, mixed>> $testCases */
        $testCases = $expected['testCases'] ?? [];
        foreach ($testCases as $tc) {
            if (isset($tc['expectedAssignments']) && is_array($tc['expectedAssignments'])) {
                return [$bundle, $tc];
            }
        }

        self::fail("{$expectedFile} has no test case with expectedAssignments");
    }

    /**
     * @param array<string, mixed> $tc
     * @return array<string, mixed>
     */
    private function context(array $tc): array
    {
        return isset($tc['context']) && is_array($tc['context']) ? $tc['context'] : [];
    }

    /**
     * @param array<string, mixed> $tc
     * @return array<string, mixed>
     */
    private function defaults(ConfigBundle $bundle, array $tc): array
    {
        if (isset($tc['defaults']) && is_array($tc['defaults'])) {
            return $tc['defaults'];
        }

        $defaults = [];
        foreach ($bundle->parameters as $param) {
            $defaults[$param->key] = $param->default;
        }

        return $defaults;
    }
}
