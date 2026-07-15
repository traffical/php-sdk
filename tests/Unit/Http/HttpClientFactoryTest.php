<?php

declare(strict_types=1);

namespace Traffical\Tests\Unit\Http;

use GuzzleHttp\Client as GuzzleClient;
use Http\Mock\Client as MockHttpClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use ReflectionObject;
use Traffical\Config\HttpConfigSource;
use Traffical\Http\HttpClientFactory;
use Traffical\Server\DecisionClient;
use Traffical\Transport\BatchingEventTransport;

/**
 * S8 mandatory timeouts: the configured per-request timeout must actually be
 * applied to the auto-discovered Guzzle client the transports send through.
 */
final class HttpClientFactoryTest extends TestCase
{
    /**
     * Reads Guzzle's private `config` array so a test can assert the timeout
     * options were baked in at construction time (PSR-18 exposes no per-request
     * options, so this is the only place a timeout can live).
     *
     * @return array<string, mixed>
     */
    private function guzzleConfig(GuzzleClient $client): array
    {
        $prop = (new ReflectionObject($client))->getProperty('config');
        /** @var array<string, mixed> $config */
        $config = $prop->getValue($client);

        return $config;
    }

    /**
     * Reads the private `httpClient` the transport actually resolved to use.
     */
    private function httpClientOf(object $transport): ClientInterface
    {
        $prop = (new ReflectionObject($transport))->getProperty('httpClient');
        $client = $prop->getValue($transport);
        self::assertInstanceOf(ClientInterface::class, $client);

        return $client;
    }

    public function testFactoryAppliesTimeoutToAutoDiscoveredGuzzleClient(): void
    {
        $client = HttpClientFactory::resolve(null, 7_000);

        self::assertInstanceOf(GuzzleClient::class, $client);
        $config = $this->guzzleConfig($client);
        self::assertSame(7.0, $config['timeout']);
        self::assertSame(7.0, $config['connect_timeout']);
    }

    public function testFactoryReturnsInjectedClientUnchanged(): void
    {
        $injected = new MockHttpClient();
        $client = HttpClientFactory::resolve($injected, 1_000);

        self::assertSame($injected, $client);
    }

    public function testEventTransportThreadsTimeoutIntoGuzzleClient(): void
    {
        $transport = new BatchingEventTransport(
            baseUrl: 'https://sdk.traffical.io',
            apiKey: 'sdk_test',
            timeoutMs: 4_500,
        );

        $client = $this->httpClientOf($transport);
        self::assertInstanceOf(GuzzleClient::class, $client);
        self::assertSame(4.5, $this->guzzleConfig($client)['timeout']);
    }

    public function testConfigSourceThreadsTimeoutIntoGuzzleClient(): void
    {
        $source = new HttpConfigSource(
            baseUrl: 'https://sdk.traffical.io',
            projectId: 'proj_test',
            env: 'production',
            apiKey: 'sdk_test',
            timeoutMs: 9_000,
        );

        $client = $this->httpClientOf($source);
        self::assertInstanceOf(GuzzleClient::class, $client);
        self::assertSame(9.0, $this->guzzleConfig($client)['timeout']);
    }

    public function testDecisionClientThreadsTimeoutIntoGuzzleClient(): void
    {
        $decisionClient = new DecisionClient(
            baseUrl: 'https://sdk.traffical.io',
            orgId: 'org_test',
            projectId: 'proj_test',
            env: 'production',
            apiKey: 'sdk_test',
            timeoutMs: 3_000,
        );

        $client = $this->httpClientOf($decisionClient);
        self::assertInstanceOf(GuzzleClient::class, $client);
        self::assertSame(3.0, $this->guzzleConfig($client)['timeout']);
    }
}
