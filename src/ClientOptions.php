<?php

declare(strict_types=1);

namespace Traffical;

use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Traffical\Config\ConfigSource;
use Traffical\Plugins\Plugin;
use Traffical\Transport\EventTransport;
use Traffical\Types\ConfigBundle;
use Traffical\Warehouse\AssignmentLogger;
use Traffical\Warehouse\CallableAssignmentLogger;

/**
 * Immutable configuration for {@see Client}. Construct with the required
 * identifiers and refine with the fluent `with*()` methods, each of which
 * returns a new instance.
 *
 * All external collaborators (PSR-18 HTTP client, PSR-17 factories, PSR-16
 * cache, PSR-3 logger, PSR-20 clock) are injectable seams; when omitted, the
 * SDK auto-discovers sensible defaults via php-http/discovery.
 */
final class ClientOptions
{
    public const DEFAULT_BASE_URL = 'https://sdk.traffical.io';
    public const DEFAULT_REFRESH_INTERVAL_MS = 60_000;
    public const DEFAULT_EVENT_BATCH_SIZE = 10;

    public readonly ?AssignmentLogger $assignmentLogger;

    /**
     * @param list<Plugin> $plugins
     */
    public function __construct(
        public readonly string $orgId,
        public readonly string $projectId,
        public readonly string $env,
        public readonly string $apiKey,
        public readonly string $baseUrl = self::DEFAULT_BASE_URL,
        public readonly ?ConfigBundle $localConfig = null,
        public readonly int $refreshIntervalMs = self::DEFAULT_REFRESH_INTERVAL_MS,
        /** "bundle" (local evaluation) or "server" (POST /v1/resolve). */
        public readonly string $evaluationMode = 'bundle',
        AssignmentLogger|callable|null $assignmentLogger = null,
        public readonly bool $disableCloudEvents = false,
        public readonly bool $deduplicateAssignmentLogger = true,
        public readonly bool $trackDecisions = true,
        public readonly int $eventBatchSize = self::DEFAULT_EVENT_BATCH_SIZE,
        public readonly string $sdkName = Version::SDK_NAME,
        public readonly string $sdkVersion = Version::SDK_VERSION,
        public readonly ?ClientInterface $httpClient = null,
        public readonly ?RequestFactoryInterface $requestFactory = null,
        public readonly ?StreamFactoryInterface $streamFactory = null,
        public readonly ?CacheInterface $cache = null,
        public readonly ?LoggerInterface $logger = null,
        public readonly ?ClockInterface $clock = null,
        public readonly ?ConfigSource $configSource = null,
        public readonly ?EventTransport $eventTransport = null,
        public readonly array $plugins = [],
    ) {
        $this->assignmentLogger = $assignmentLogger === null
            ? null
            : ($assignmentLogger instanceof AssignmentLogger
                ? $assignmentLogger
                : new CallableAssignmentLogger($assignmentLogger));
    }

    public function withBaseUrl(string $baseUrl): self
    {
        return $this->copy(baseUrl: $baseUrl);
    }

    public function withLocalConfig(ConfigBundle $bundle): self
    {
        return $this->copy(localConfig: $bundle);
    }

    public function withEvaluationMode(string $mode): self
    {
        return $this->copy(evaluationMode: $mode);
    }

    public function withAssignmentLogger(AssignmentLogger|callable $logger): self
    {
        return $this->copy(assignmentLogger: $logger);
    }

    public function withDisableCloudEvents(bool $disable = true): self
    {
        return $this->copy(disableCloudEvents: $disable);
    }

    public function withDeduplicateAssignmentLogger(bool $dedup): self
    {
        return $this->copy(deduplicateAssignmentLogger: $dedup);
    }

    public function withTrackDecisions(bool $track): self
    {
        return $this->copy(trackDecisions: $track);
    }

    public function withHttpClient(ClientInterface $client): self
    {
        return $this->copy(httpClient: $client);
    }

    public function withRequestFactory(RequestFactoryInterface $factory): self
    {
        return $this->copy(requestFactory: $factory);
    }

    public function withStreamFactory(StreamFactoryInterface $factory): self
    {
        return $this->copy(streamFactory: $factory);
    }

    public function withCache(CacheInterface $cache): self
    {
        return $this->copy(cache: $cache);
    }

    public function withLogger(LoggerInterface $logger): self
    {
        return $this->copy(logger: $logger);
    }

    public function withClock(ClockInterface $clock): self
    {
        return $this->copy(clock: $clock);
    }

    public function withConfigSource(ConfigSource $source): self
    {
        return $this->copy(configSource: $source);
    }

    public function withEventTransport(EventTransport $transport): self
    {
        return $this->copy(eventTransport: $transport);
    }

    /**
     * @param list<Plugin> $plugins
     */
    public function withPlugins(array $plugins): self
    {
        return $this->copy(plugins: $plugins);
    }

    /**
     * Reconstructs the value object overriding only the provided seams. A null
     * argument means "keep the current value"; since every `with*()` method
     * passes a non-null value, this preserves type safety without a sentinel.
     *
     * @param list<Plugin>|null $plugins
     */
    private function copy(
        ?string $baseUrl = null,
        ?ConfigBundle $localConfig = null,
        ?string $evaluationMode = null,
        AssignmentLogger|callable|null $assignmentLogger = null,
        ?bool $disableCloudEvents = null,
        ?bool $deduplicateAssignmentLogger = null,
        ?bool $trackDecisions = null,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?CacheInterface $cache = null,
        ?LoggerInterface $logger = null,
        ?ClockInterface $clock = null,
        ?ConfigSource $configSource = null,
        ?EventTransport $eventTransport = null,
        ?array $plugins = null,
    ): self {
        return new self(
            orgId: $this->orgId,
            projectId: $this->projectId,
            env: $this->env,
            apiKey: $this->apiKey,
            baseUrl: $baseUrl ?? $this->baseUrl,
            localConfig: $localConfig ?? $this->localConfig,
            refreshIntervalMs: $this->refreshIntervalMs,
            evaluationMode: $evaluationMode ?? $this->evaluationMode,
            assignmentLogger: $assignmentLogger ?? $this->assignmentLogger,
            disableCloudEvents: $disableCloudEvents ?? $this->disableCloudEvents,
            deduplicateAssignmentLogger: $deduplicateAssignmentLogger ?? $this->deduplicateAssignmentLogger,
            trackDecisions: $trackDecisions ?? $this->trackDecisions,
            eventBatchSize: $this->eventBatchSize,
            sdkName: $this->sdkName,
            sdkVersion: $this->sdkVersion,
            httpClient: $httpClient ?? $this->httpClient,
            requestFactory: $requestFactory ?? $this->requestFactory,
            streamFactory: $streamFactory ?? $this->streamFactory,
            cache: $cache ?? $this->cache,
            logger: $logger ?? $this->logger,
            clock: $clock ?? $this->clock,
            configSource: $configSource ?? $this->configSource,
            eventTransport: $eventTransport ?? $this->eventTransport,
            plugins: $plugins ?? $this->plugins,
        );
    }
}
