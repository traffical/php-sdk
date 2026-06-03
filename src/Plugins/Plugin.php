<?php

declare(strict_types=1);

namespace Traffical\Plugins;

use Traffical\Types\ConfigBundle;
use Traffical\Types\DecisionResult;
use Traffical\Types\ExposureEvent;
use Traffical\Types\TrackEvent;

/**
 * Plugin contract. Every plugin has a unique name and a priority (higher runs
 * first). The remaining methods are lifecycle hooks; extend
 * {@see AbstractPlugin} to inherit no-op defaults and override only what you
 * need.
 */
interface Plugin
{
    /** Unique plugin name (used for dedup and lookup). */
    public function name(): string;

    /** Higher priority runs first. */
    public function priority(): int;

    /** Called once after client initialization completes. */
    public function onInitialize(PluginHost $client): void;

    /** Called when the config bundle is loaded or refreshed. */
    public function onConfigUpdate(ConfigBundle $bundle): void;

    /**
     * Called before a decision; may return a modified context.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function onBeforeDecision(array $context): array;

    /** Called after a decision is made. */
    public function onDecision(DecisionResult $decision): void;

    /**
     * Called after parameters are resolved via getParams().
     *
     * @param array<string, mixed> $params
     */
    public function onResolve(array $params): void;

    /** Called before an exposure is tracked. Return false to cancel. */
    public function onExposure(ExposureEvent $event): bool;

    /** Called before a track event is sent. Return false to cancel. */
    public function onTrack(TrackEvent $event): bool;

    /** Called when the client is destroyed. */
    public function onDestroy(): void;
}
