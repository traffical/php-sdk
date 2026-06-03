<?php

declare(strict_types=1);

namespace Traffical\Server;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Traffical\Types\ServerResolveResponse;

/**
 * I/O client for Traffical server-evaluated resolution (POST /v1/resolve).
 * Used when evaluationMode = "server": the edge worker resolves all policies
 * (including per-entity) and returns assignments + metadata in one round trip.
 */
final class DecisionClient
{
    private readonly ClientInterface $httpClient;
    private readonly RequestFactoryInterface $requestFactory;
    private readonly StreamFactoryInterface $streamFactory;
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $orgId,
        private readonly string $projectId,
        private readonly string $env,
        private readonly string $apiKey,
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

    /**
     * Resolves all parameters on the edge worker.
     *
     * @param array<string, mixed> $context
     * @param list<string>|null $parameters Optional subset of parameter keys.
     */
    public function resolve(array $context, ?array $parameters = null): ?ServerResolveResponse
    {
        $payload = ['context' => $context, 'env' => $this->env];
        if ($parameters !== null) {
            $payload['parameters'] = $parameters;
        }

        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->logger->warning('[Traffical] Failed to encode resolve request', ['error' => $e->getMessage()]);

            return null;
        }

        $request = $this->requestFactory
            ->createRequest('POST', rtrim($this->baseUrl, '/') . '/v1/resolve')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->apiKey)
            ->withHeader('X-Org-Id', $this->orgId)
            ->withHeader('X-Project-Id', $this->projectId)
            ->withHeader('X-Env', $this->env)
            ->withBody($this->streamFactory->createStream($body));

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            $this->logger->warning('[Traffical] Resolve request failed', ['error' => $e->getMessage()]);

            return null;
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            $this->logger->warning('[Traffical] Resolve returned non-2xx', ['status' => $status]);

            return null;
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->logger->warning('[Traffical] Resolve response was not valid JSON', ['error' => $e->getMessage()]);

            return null;
        }

        return ServerResolveResponse::fromArray($data);
    }
}
