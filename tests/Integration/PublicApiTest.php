<?php

declare(strict_types=1);

namespace Traffical\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Traffical\Client;
use Traffical\ClientOptions;
use Traffical\Tests\Conformance\CapturingTransport;
use Traffical\TrackOptions;
use Traffical\Types\TrackEvent;

/**
 * A1 public-API surface: track() options bag, close() teardown, and the
 * canonical *Ms / dedup options.
 */
final class PublicApiTest extends TestCase
{
    private function client(CapturingTransport $capture): Client
    {
        return new Client(new ClientOptions(
            orgId: 'org_test',
            projectId: 'proj_test',
            env: 'production',
            apiKey: 'sdk_test',
            eventTransport: $capture,
        ));
    }

    public function testTrackOptionsBagPopulatesEventFields(): void
    {
        $capture = new CapturingTransport();
        $client = $this->client($capture);

        $client->track('purchase', ['orderId' => 'o-1'], new TrackOptions(
            decisionId: 'dec_1',
            unitKey: 'user-abc',
            value: 49.0,
            values: ['revenue' => 49.0, 'items' => 3],
            eventTimestamp: '2024-06-02T09:00:00.000Z',
        ));

        self::assertCount(1, $capture->events);
        $event = $capture->events[0];
        self::assertInstanceOf(TrackEvent::class, $event);
        self::assertSame('user-abc', $event->unitKey);
        self::assertSame('dec_1', $event->decisionId);
        self::assertSame(49.0, $event->value);
        self::assertSame(['revenue' => 49.0, 'items' => 3.0], $event->values);
        self::assertSame('2024-06-02T09:00:00.000Z', $event->eventTimestamp);
    }

    public function testCloseFlushesEvents(): void
    {
        $capture = new CapturingTransport();
        $client = $this->client($capture);
        $client->track('ping');

        self::assertFalse($capture->flushed);
        $client->close();
        self::assertTrue($capture->flushed, 'close() must await a final flush');
    }

    public function testExposureDedupCanBeDisabled(): void
    {
        // With dedup off, a second identical decision re-exposes the same layer.
        // (Covered structurally here via the option; S4 shape is verified by the
        // exposure_shape conformance test.)
        $options = new ClientOptions(
            orgId: 'o',
            projectId: 'p',
            env: 'production',
            apiKey: 'k',
            deduplicateExposures: false,
        );
        self::assertFalse($options->deduplicateExposures);
        self::assertSame(1_800_000, $options->exposureSessionTtlMs);
        self::assertSame(10, $options->batchSize);
        self::assertSame(10_000, $options->configTimeoutMs);
        self::assertSame(5_000, $options->resolveTimeoutMs);

        $withMs = $options->withBatchSize(25)->withExposureSessionTtlMs(60_000);
        self::assertSame(25, $withMs->batchSize);
        self::assertSame(60_000, $withMs->exposureSessionTtlMs);
    }
}
