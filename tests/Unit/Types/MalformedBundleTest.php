<?php

declare(strict_types=1);

namespace Traffical\Tests\Unit\Types;

use Http\Mock\Client as MockHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Traffical\Client;
use Traffical\ClientOptions;
use Traffical\Config\HttpConfigSource;
use Traffical\Types\ConfigBundle;
use Traffical\Types\MalformedBundleException;

/**
 * S8: malformed bundles are rejected at parse and the SDK fails open — a bad
 * bundle never crashes decide()/getParams() nor replaces a good fallback.
 */
final class MalformedBundleTest extends TestCase
{
    public function testBucketCountBelowOneIsRejected(): void
    {
        $this->expectException(MalformedBundleException::class);
        ConfigBundle::fromArray([
            'version' => 'v1',
            'orgId' => 'o',
            'projectId' => 'p',
            'env' => 'production',
            'hashing' => ['unitKey' => 'userId', 'bucketCount' => 0],
            'parameters' => [],
            'layers' => [],
        ]);
    }

    public function testEmptyUnitKeyIsRejected(): void
    {
        $this->expectException(MalformedBundleException::class);
        ConfigBundle::fromArray([
            'version' => 'v1',
            'orgId' => 'o',
            'projectId' => 'p',
            'env' => 'production',
            'hashing' => ['unitKey' => '  ', 'bucketCount' => 1000],
            'parameters' => [],
            'layers' => [],
        ]);
    }

    public function testMissingHashingIsRejected(): void
    {
        $this->expectException(MalformedBundleException::class);
        ConfigBundle::fromArray([
            'version' => 'v1',
            'orgId' => 'o',
            'projectId' => 'p',
            'env' => 'production',
            'parameters' => [],
            'layers' => [],
        ]);
    }

    public function testClientFailsOpenToLocalConfigOnMalformedFetch(): void
    {
        $localConfig = ConfigBundle::fromArray([
            'version' => 'v-local',
            'orgId' => 'org_test',
            'projectId' => 'proj_test',
            'env' => 'production',
            'hashing' => ['unitKey' => 'userId', 'bucketCount' => 1000],
            'parameters' => [[
                'key' => 'ui.theme',
                'type' => 'string',
                'default' => 'dark',
                'layerId' => 'layer_ui',
                'namespace' => 'ui',
            ]],
            'layers' => [],
        ]);

        // The remote returns a structurally-broken bundle (bucketCount 0).
        $psr17 = new Psr17Factory();
        $mock = new MockHttpClient();
        $mock->setDefaultResponse(
            $psr17->createResponse(200)->withBody($psr17->createStream(
                (string) json_encode([
                    'version' => 'v-remote',
                    'orgId' => 'org_test',
                    'projectId' => 'proj_test',
                    'env' => 'production',
                    'hashing' => ['unitKey' => 'userId', 'bucketCount' => 0],
                    'parameters' => [],
                    'layers' => [],
                ]),
            )),
        );

        $configSource = new HttpConfigSource(
            baseUrl: 'https://sdk.traffical.io',
            projectId: 'proj_test',
            env: 'production',
            apiKey: 'sdk_test',
            httpClient: $mock,
            requestFactory: $psr17,
        );

        $client = new Client(new ClientOptions(
            orgId: 'org_test',
            projectId: 'proj_test',
            env: 'production',
            apiKey: 'sdk_test',
            localConfig: $localConfig,
            disableCloudEvents: true,
            configSource: $configSource,
        ));

        // decide() must not throw, and must serve the good localConfig default.
        $decision = $client->decide(['userId' => 'user-abc'], ['ui.theme' => 'light']);
        self::assertSame('dark', $decision->assignments['ui.theme']);
        self::assertSame('v-local', $client->getConfigVersion());
    }
}
