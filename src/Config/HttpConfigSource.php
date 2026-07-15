<?php

declare(strict_types=1);

namespace Traffical\Config;

use Http\Discovery\Psr17FactoryDiscovery;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Traffical\Http\HttpClientFactory;
use Traffical\Types\ConfigBundle;
use Traffical\Types\MalformedBundleException;

/**
 * Fetches the config bundle over HTTP from the Traffical control plane using a
 * PSR-18 client. Implements ETag / If-None-Match conditional requests and 304
 * handling so repeated loads are cheap, and keeps the last-known bundle so
 * transient failures degrade gracefully.
 */
final class HttpConfigSource implements ConfigSource
{
    private readonly ClientInterface $httpClient;
    private readonly RequestFactoryInterface $requestFactory;
    private readonly LoggerInterface $logger;

    private ?string $etag = null;
    private ?ConfigBundle $lastBundle = null;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $projectId,
        private readonly string $env,
        private readonly string $apiKey,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?LoggerInterface $logger = null,
        /** Config-fetch request timeout (ms), applied to auto-discovered Guzzle clients. */
        private readonly int $timeoutMs = 10_000,
    ) {
        $this->httpClient = HttpClientFactory::resolve($httpClient, $this->timeoutMs);
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->logger = $logger ?? new NullLogger();
    }

    public function load(): ?ConfigBundle
    {
        $url = sprintf(
            '%s/v1/config/%s?env=%s',
            rtrim($this->baseUrl, '/'),
            rawurlencode($this->projectId),
            rawurlencode($this->env),
        );

        $request = $this->requestFactory->createRequest('GET', $url)
            ->withHeader('Accept', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->apiKey);

        if ($this->etag !== null) {
            $request = $request->withHeader('If-None-Match', $this->etag);
        }

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            $this->logger->warning('[Traffical] Config fetch failed', ['error' => $e->getMessage()]);

            return $this->lastBundle;
        }

        $status = $response->getStatusCode();

        if ($status === 304) {
            return $this->lastBundle;
        }

        if ($status < 200 || $status >= 300) {
            $this->logger->warning('[Traffical] Config fetch returned non-2xx', ['status' => $status]);

            return $this->lastBundle;
        }

        try {
            $bundle = ConfigBundle::fromJson((string) $response->getBody());
        } catch (JsonException $e) {
            $this->logger->warning('[Traffical] Config response was not valid JSON', ['error' => $e->getMessage()]);

            return $this->lastBundle;
        } catch (MalformedBundleException $e) {
            // S8: a structurally-bad bundle must be discarded, never replace the
            // last-good one. Keep serving what we have (or nothing).
            $this->logger->warning('[Traffical] Discarding malformed config bundle', ['error' => $e->getMessage()]);

            return $this->lastBundle;
        }

        $etag = $response->getHeaderLine('ETag');
        $this->etag = $etag !== '' ? $etag : null;
        $this->lastBundle = $bundle;

        return $bundle;
    }
}
