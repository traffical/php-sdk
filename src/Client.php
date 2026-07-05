<?php

declare(strict_types=1);

namespace Traffical;

use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Traffical\Config\CachedConfigSource;
use Traffical\Config\ConfigSource;
use Traffical\Config\HttpConfigSource;
use Traffical\Dedup\AssignmentDedupStrategy;
use Traffical\Dedup\AssignmentLoggerDedup;
use Traffical\Dedup\CachedAssignmentLoggerDedup;
use Traffical\Dedup\DecisionDeduplicator;
use Traffical\Engine\ResolutionEngine;
use Traffical\Id\IdGenerator;
use Traffical\Plugins\Plugin;
use Traffical\Plugins\PluginHost;
use Traffical\Plugins\PluginManager;
use Traffical\Server\DecisionClient;
use Traffical\Transport\BatchingEventTransport;
use Traffical\Transport\EventTransport;
use Traffical\Transport\NullEventTransport;
use Traffical\Types\AssignmentLogEntry;
use Traffical\Types\AssignmentType;
use Traffical\Types\ConfigBundle;
use Traffical\Types\DecisionEvent;
use Traffical\Types\DecisionResult;
use Traffical\Types\ExposureEvent;
use Traffical\Types\ServerResolveResponse;
use Traffical\Types\TrackAttribution;
use Traffical\Types\TrackEvent;
use Traffical\Warehouse\AssignmentLogger;

/**
 * The Traffical PHP client.
 *
 * Resolves parameters locally from a cached config bundle (bundle mode) or via
 * the edge worker (server mode), emits decision/exposure/track events through a
 * batching transport, drives warehouse-native assignment logging, and runs the
 * plugin pipeline. Designed for the PHP-FPM request lifecycle: events flush on
 * shutdown after the response is returned to the client.
 */
final class Client implements PluginHost
{
    private const DECISION_CACHE_MAX = 1000;

    private readonly LoggerInterface $logger;
    private readonly IdGenerator $ids;
    private readonly PluginManager $plugins;
    private readonly EventTransport $transport;
    private readonly DecisionDeduplicator $decisionDedup;
    private readonly ?AssignmentLogger $assignmentLogger;
    private readonly ?AssignmentDedupStrategy $assignmentDedup;
    private readonly ?ConfigSource $configSource;
    private readonly ?DecisionClient $decisionClient;

    private bool $bundleLoaded = false;
    private ?ConfigBundle $bundle = null;

    /** @var array<string, ServerResolveResponse> */
    private array $serverCache = [];
    private ?ServerResolveResponse $lastServerResponse = null;

    /** @var array<string, DecisionResult> */
    private array $decisionCache = [];

    public function __construct(
        private readonly ClientOptions $options,
    ) {
        $this->logger = $options->logger ?? new NullLogger();
        $this->ids = new IdGenerator($options->clock);
        $this->plugins = new PluginManager($this->logger);
        foreach ($options->plugins as $plugin) {
            $this->plugins->register($plugin);
        }

        $this->assignmentLogger = $options->assignmentLogger;
        $this->assignmentDedup = $this->buildAssignmentDedup();
        $this->decisionDedup = new DecisionDeduplicator();
        $this->transport = $this->buildTransport();
        $this->configSource = $options->evaluationMode === 'server' ? null : $this->buildConfigSource();
        $this->decisionClient = $options->evaluationMode === 'server' ? $this->buildDecisionClient() : null;

        if ($options->localConfig !== null) {
            $this->bundle = $options->localConfig;
        }

        $this->plugins->runInitialize($this);
        $this->registerShutdownFlush();
    }

    /**
     * Convenience constructor matching the JS createTrafficalClient ergonomics.
     */
    public static function create(ClientOptions $options): self
    {
        return new self($options);
    }

    /**
     * Resolves parameters with defaults as fallback.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    public function getParams(array $context, array $defaults): array
    {
        if ($this->options->evaluationMode === 'server') {
            $resp = $this->serverResolve($context);
            $params = $resp !== null
                ? $this->mergeServerAssignments($defaults, $resp->assignments)
                : ResolutionEngine::resolveParameters($this->getBundle(), $context, $defaults);
        } else {
            $params = ResolutionEngine::resolveParameters($this->getBundle(), $context, $defaults);
        }

        $this->plugins->runResolve($params);

        return $params;
    }

    /**
     * Makes a decision with full metadata for tracking.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $defaults
     */
    public function decide(array $context, array $defaults): DecisionResult
    {
        $context = $this->plugins->runBeforeDecision($context);
        $start = microtime(true);
        $timestamp = $this->nowIso();

        if ($this->options->evaluationMode === 'server') {
            $resp = $this->serverResolve($context);
            if ($resp !== null) {
                $decision = new DecisionResult(
                    decisionId: $resp->decisionId,
                    assignments: $this->mergeServerAssignments($defaults, $resp->assignments),
                    // Snapshot the state version this resolve response was
                    // evaluated against; events stamp from the snapshot.
                    metadata: $resp->metadata->withConfigVersion($resp->stateVersion),
                );
            } else {
                $decision = $this->decideLocally($context, $defaults, $timestamp);
            }
        } else {
            $decision = $this->decideLocally($context, $defaults, $timestamp);
        }

        $this->cacheDecision($decision);

        if ($this->options->trackDecisions) {
            $this->trackDecision($decision, (microtime(true) - $start) * 1000, array_keys($defaults));
        }

        $this->emitAssignmentLogEntries($decision, AssignmentType::Decision);
        $this->plugins->runDecision($decision);

        return $decision;
    }

    /**
     * Tracks an exposure event for a decision.
     */
    public function trackExposure(DecisionResult $decision): void
    {
        $unitKey = $decision->metadata->unitKeyValue;
        if ($unitKey === '') {
            return;
        }

        // The assignment logger fires regardless of disableCloudEvents.
        $this->emitAssignmentLogEntries($decision, AssignmentType::Exposure);

        if ($this->options->disableCloudEvents) {
            return;
        }

        $event = new ExposureEvent(
            decisionId: $decision->decisionId,
            orgId: $this->options->orgId,
            projectId: $this->options->projectId,
            env: $this->options->env,
            unitKey: $unitKey,
            timestamp: $this->nowIso(),
            assignments: $decision->assignments,
            layers: $decision->metadata->layers,
            context: $decision->metadata->filteredContext,
            id: $this->ids->exposure(),
            sdkName: $this->options->sdkName,
            sdkVersion: $this->options->sdkVersion,
            configVersion: $decision->metadata->configVersion,
        );

        if ($this->plugins->runExposure($event)) {
            $this->transport->log($event);
        }
    }

    /**
     * Tracks a user event (conversion, engagement, etc.).
     *
     * @param array<string, mixed>|null $properties
     */
    public function track(string $event, ?array $properties = null, ?string $decisionId = null, ?string $unitKey = null): void
    {
        if ($this->options->disableCloudEvents) {
            return;
        }

        $value = null;
        if ($properties !== null && isset($properties['value']) && (is_int($properties['value']) || is_float($properties['value']))) {
            $value = (float) $properties['value'];
        }

        $trackEvent = new TrackEvent(
            event: $event,
            orgId: $this->options->orgId,
            projectId: $this->options->projectId,
            env: $this->options->env,
            unitKey: $unitKey ?? '',
            timestamp: $this->nowIso(),
            value: $value,
            properties: $properties,
            decisionId: $decisionId,
            attribution: $this->getAttributionFromCache($decisionId),
            id: $this->ids->trackEvent(),
            sdkName: $this->options->sdkName,
            sdkVersion: $this->options->sdkVersion,
        );

        if ($this->plugins->runTrack($trackEvent)) {
            $this->transport->log($trackEvent);
        }
    }

    /**
     * Flushes pending events immediately.
     */
    public function flushEvents(): void
    {
        $this->transport->flush();
    }

    /**
     * Reloads the config bundle (bundle mode) or clears the server cache.
     */
    public function refreshConfig(): void
    {
        if ($this->options->evaluationMode === 'server') {
            $this->serverCache = [];

            return;
        }

        $this->bundleLoaded = false;
        $this->getBundle();
    }

    public function getConfigVersion(): ?string
    {
        if ($this->options->evaluationMode === 'server') {
            return $this->lastServerResponse?->stateVersion;
        }

        return $this->getBundle()?->version;
    }

    /**
     * Registers a plugin after construction.
     */
    public function registerPlugin(Plugin $plugin): bool
    {
        $registered = $this->plugins->register($plugin);
        if ($registered) {
            $plugin->onInitialize($this);
        }

        return $registered;
    }

    public function plugins(): PluginManager
    {
        return $this->plugins;
    }

    /**
     * Flushes events and runs plugin teardown. Call at end of a long-lived
     * process; for typical FPM requests the shutdown handler does this.
     */
    public function destroy(): void
    {
        $this->plugins->runDestroy();
        $this->flushEvents();
    }

    // =========================================================================
    // Internals
    // =========================================================================

    /**
     * Resolves a decision against the local bundle and snapshots the config
     * version the SDK evaluated against into the decision metadata. Decision,
     * exposure, and assignment-log emissions all stamp `configVersion` from
     * that snapshot so a config refresh between decide() and trackExposure()
     * cannot skew it. Omitted on cold start (no bundle yet).
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $defaults
     */
    private function decideLocally(array $context, array $defaults, string $timestamp): DecisionResult
    {
        $bundle = $this->getBundle();
        $decision = ResolutionEngine::decide(
            $bundle,
            $context,
            $defaults,
            null,
            $this->ids->decision(),
            $timestamp,
        );

        return new DecisionResult(
            decisionId: $decision->decisionId,
            assignments: $decision->assignments,
            metadata: $decision->metadata->withConfigVersion($bundle?->version),
        );
    }

    private function emitAssignmentLogEntries(DecisionResult $decision, AssignmentType $type): void
    {
        if ($this->assignmentLogger === null) {
            return;
        }
        $unitKey = $decision->metadata->unitKeyValue;
        if ($unitKey === '') {
            return;
        }

        $configVersion = $decision->metadata->configVersion;

        foreach ($decision->metadata->layers as $layer) {
            if ($layer->policyId === null || $layer->allocationName === null) {
                continue;
            }

            if ($this->assignmentDedup !== null) {
                $key = AssignmentLoggerDedup::key($unitKey, $layer->policyId, $layer->allocationName, $type->value);
                if (!$this->assignmentDedup->shouldEmit($key)) {
                    continue;
                }
            }

            $this->assignmentLogger->log(new AssignmentLogEntry(
                unitKey: $unitKey,
                policyId: $layer->policyId,
                allocationName: $layer->allocationName,
                timestamp: $decision->metadata->timestamp,
                layerId: $layer->layerId,
                orgId: $this->options->orgId,
                projectId: $this->options->projectId,
                env: $this->options->env,
                type: $type,
                policyKey: $layer->policyKey,
                allocationKey: $layer->allocationKey,
                allocationId: $layer->allocationId,
                sdkName: $this->options->sdkName,
                sdkVersion: $this->options->sdkVersion,
                properties: $decision->metadata->filteredContext,
                decisionId: $decision->decisionId,
                anonymousId: null,
                id: $this->ids->assignment(),
                bucket: $layer->bucket >= 0 ? $layer->bucket : null,
                probability: $layer->probability,
                modelVersion: $layer->modelVersion,
                configVersion: $configVersion,
            ));
        }
    }

    /**
     * @param list<string> $requestedParameters
     */
    private function trackDecision(DecisionResult $decision, float $latencyMs, array $requestedParameters): void
    {
        if ($this->options->disableCloudEvents) {
            return;
        }
        $unitKey = $decision->metadata->unitKeyValue;
        if ($unitKey === '') {
            return;
        }

        $hash = DecisionDeduplicator::hashAssignments($decision->assignments);
        if (!$this->decisionDedup->checkAndMark($unitKey, $hash)) {
            return;
        }

        $this->transport->log(new DecisionEvent(
            id: $decision->decisionId,
            orgId: $this->options->orgId,
            projectId: $this->options->projectId,
            env: $this->options->env,
            unitKey: $unitKey,
            timestamp: $decision->metadata->timestamp,
            assignments: $decision->assignments,
            layers: $decision->metadata->layers,
            requestedParameters: $requestedParameters,
            latencyMs: $latencyMs,
            context: $decision->metadata->filteredContext,
            sdkName: $this->options->sdkName,
            sdkVersion: $this->options->sdkVersion,
            configVersion: $decision->metadata->configVersion,
        ));
    }

    private function getBundle(): ?ConfigBundle
    {
        if ($this->bundleLoaded) {
            return $this->bundle;
        }
        $this->bundleLoaded = true;

        $loaded = $this->configSource?->load();
        $this->bundle = $loaded ?? $this->options->localConfig;

        if ($this->bundle !== null) {
            $this->plugins->runConfigUpdate($this->bundle);
        }

        return $this->bundle;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function serverResolve(array $context): ?ServerResolveResponse
    {
        if ($this->decisionClient === null) {
            return null;
        }
        $key = md5((string) json_encode($context));
        if (isset($this->serverCache[$key])) {
            return $this->serverCache[$key];
        }

        $resp = $this->decisionClient->resolve($context);
        if ($resp !== null) {
            $this->serverCache[$key] = $resp;
            $this->lastServerResponse = $resp;
        }

        return $resp;
    }

    /**
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $serverAssignments
     * @return array<string, mixed>
     */
    private function mergeServerAssignments(array $defaults, array $serverAssignments): array
    {
        $result = $defaults;
        foreach ($serverAssignments as $key => $value) {
            if (array_key_exists($key, $result)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function cacheDecision(DecisionResult $decision): void
    {
        if (count($this->decisionCache) >= self::DECISION_CACHE_MAX) {
            $firstKey = array_key_first($this->decisionCache);
            if ($firstKey !== null) {
                unset($this->decisionCache[$firstKey]);
            }
        }
        $this->decisionCache[$decision->decisionId] = $decision;
    }

    /**
     * @return list<TrackAttribution>|null
     */
    private function getAttributionFromCache(?string $decisionId): ?array
    {
        if ($decisionId === null) {
            return null;
        }
        $cached = $this->decisionCache[$decisionId] ?? null;
        if ($cached === null) {
            return null;
        }

        $attribution = [];
        foreach ($cached->metadata->layers as $layer) {
            if ($layer->policyId !== null && $layer->allocationName !== null) {
                $attribution[] = new TrackAttribution(
                    layerId: $layer->layerId,
                    policyId: $layer->policyId,
                    allocationName: $layer->allocationName,
                );
            }
        }

        return count($attribution) > 0 ? $attribution : null;
    }

    private function buildAssignmentDedup(): ?AssignmentDedupStrategy
    {
        if ($this->options->assignmentLogger === null || !$this->options->deduplicateAssignmentLogger) {
            return null;
        }

        return $this->options->cache !== null
            ? new CachedAssignmentLoggerDedup($this->options->cache)
            : new AssignmentLoggerDedup();
    }

    private function buildTransport(): EventTransport
    {
        if ($this->options->disableCloudEvents) {
            return new NullEventTransport();
        }
        if ($this->options->eventTransport !== null) {
            return $this->options->eventTransport;
        }

        return new BatchingEventTransport(
            baseUrl: $this->options->baseUrl,
            apiKey: $this->options->apiKey,
            batchSize: $this->options->eventBatchSize,
            httpClient: $this->options->httpClient,
            requestFactory: $this->options->requestFactory,
            streamFactory: $this->options->streamFactory,
            logger: $this->logger,
        );
    }

    private function buildConfigSource(): ConfigSource
    {
        if ($this->options->configSource !== null) {
            return $this->options->configSource;
        }

        $http = new HttpConfigSource(
            baseUrl: $this->options->baseUrl,
            projectId: $this->options->projectId,
            env: $this->options->env,
            apiKey: $this->options->apiKey,
            httpClient: $this->options->httpClient,
            requestFactory: $this->options->requestFactory,
            logger: $this->logger,
        );

        if ($this->options->cache !== null) {
            return new CachedConfigSource(
                inner: $http,
                cache: $this->options->cache,
                projectId: $this->options->projectId,
                env: $this->options->env,
                ttlSeconds: max(1, intdiv($this->options->refreshIntervalMs, 1000)),
            );
        }

        return $http;
    }

    private function buildDecisionClient(): DecisionClient
    {
        return new DecisionClient(
            baseUrl: $this->options->baseUrl,
            orgId: $this->options->orgId,
            projectId: $this->options->projectId,
            env: $this->options->env,
            apiKey: $this->options->apiKey,
            httpClient: $this->options->httpClient,
            requestFactory: $this->options->requestFactory,
            streamFactory: $this->options->streamFactory,
            logger: $this->logger,
        );
    }

    private function registerShutdownFlush(): void
    {
        register_shutdown_function(function (): void {
            // Return the response to the client first (FPM), then flush events.
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            $this->flushEvents();
        });
    }

    private function nowIso(): string
    {
        $now = $this->options->clock?->now() ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $now = $now->setTimezone(new DateTimeZone('UTC'));

        return $now->format('Y-m-d\TH:i:s.v\Z');
    }
}
