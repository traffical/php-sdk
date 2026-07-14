<?php

declare(strict_types=1);

namespace Traffical\Tests\Unit\Transport;

use Http\Mock\Client as MockHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Traffical\Transport\BatchingEventTransport;
use Traffical\Types\TrackEvent;

/**
 * S8 event-delivery behavior for the request-model batching transport.
 */
final class BatchingEventTransportTest extends TestCase
{
    private function event(string $name): TrackEvent
    {
        return new TrackEvent(
            event: $name,
            orgId: 'org_test',
            projectId: 'proj_test',
            env: 'production',
            unitKey: 'user-abc',
            timestamp: '2024-06-02T10:00:00.000Z',
        );
    }

    public function testHttp401PermanentlyDisablesDelivery(): void
    {
        $psr17 = new Psr17Factory();
        $mock = new MockHttpClient();
        $mock->addResponse($psr17->createResponse(401));

        $transport = new BatchingEventTransport(
            baseUrl: 'https://sdk.traffical.io',
            apiKey: 'bad_key',
            batchSize: 1,
            httpClient: $mock,
            requestFactory: $psr17,
            streamFactory: $psr17,
        );

        // First event triggers a flush -> 401 -> delivery disabled.
        $transport->log($this->event('a'));
        self::assertCount(1, $mock->getRequests());

        // Subsequent events are dropped without any further HTTP calls.
        $transport->log($this->event('b'));
        $transport->log($this->event('c'));
        $transport->flush();
        self::assertCount(1, $mock->getRequests(), 'no further delivery after a 401');
        self::assertSame(0, $transport->pendingCount());
    }

    public function testBatchesFlushAtBatchSize(): void
    {
        $psr17 = new Psr17Factory();
        $mock = new MockHttpClient();
        $mock->setDefaultResponse($psr17->createResponse(200));

        $transport = new BatchingEventTransport(
            baseUrl: 'https://sdk.traffical.io',
            apiKey: 'sdk_test',
            batchSize: 2,
            httpClient: $mock,
            requestFactory: $psr17,
            streamFactory: $psr17,
        );

        $transport->log($this->event('a'));
        self::assertCount(0, $mock->getRequests(), 'no flush before batch is full');
        self::assertSame(1, $transport->pendingCount());

        $transport->log($this->event('b'));
        self::assertCount(1, $mock->getRequests(), 'flush at batchSize');
        self::assertSame(0, $transport->pendingCount());
    }
}
