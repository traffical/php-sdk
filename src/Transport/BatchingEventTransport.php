<?php

declare(strict_types=1);

namespace Traffical\Transport;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Traffical\Types\TrackableEvent;

/**
 * Buffers events in memory and POSTs them to /v1/events/batch via a PSR-18
 * client. Auto-flushes once the batch size is reached; otherwise the host
 * flushes on shutdown (see {@see \Traffical\Client}). All delivery is
 * fire-and-forget: failures are logged via PSR-3 and never thrown.
 */
final class BatchingEventTransport implements EventTransport
{
    private readonly ClientInterface $httpClient;
    private readonly RequestFactoryInterface $requestFactory;
    private readonly StreamFactoryInterface $streamFactory;
    private readonly LoggerInterface $logger;

    /** @var list<TrackableEvent> */
    private array $queue = [];

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int $batchSize = 10,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->httpClient = $httpClient ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
        $this->logger = $logger ?? new NullLogger();
    }

    public function log(TrackableEvent $event): void
    {
        $this->queue[] = $event;

        if (count($this->queue) >= $this->batchSize) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if (count($this->queue) === 0) {
            return;
        }

        $events = $this->queue;
        $this->queue = [];

        $payload = ['events' => array_map(static fn (TrackableEvent $e): array => $e->toArray(), $events)];

        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->warning('[Traffical] Failed to encode event batch', ['error' => $e->getMessage()]);

            return;
        }

        $request = $this->requestFactory
            ->createRequest('POST', rtrim($this->baseUrl, '/') . '/v1/events/batch')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->apiKey)
            ->withBody($this->streamFactory->createStream($body));

        try {
            $response = $this->httpClient->sendRequest($request);
            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                $this->logger->warning('[Traffical] Event batch returned non-2xx', ['status' => $status]);
            }
        } catch (ClientExceptionInterface $e) {
            // Fire-and-forget: log and drop. The host request must not fail
            // because analytics delivery failed.
            $this->logger->warning('[Traffical] Event batch delivery failed', ['error' => $e->getMessage()]);
        }
    }

    public function pendingCount(): int
    {
        return count($this->queue);
    }
}
