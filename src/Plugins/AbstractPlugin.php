<?php

declare(strict_types=1);

namespace Traffical\Plugins;

use Traffical\Types\ConfigBundle;
use Traffical\Types\DecisionResult;
use Traffical\Types\ExposureEvent;
use Traffical\Types\TrackEvent;

/**
 * No-op base implementation of {@see Plugin}. Concrete plugins extend this and
 * override only the hooks they care about.
 */
abstract class AbstractPlugin implements Plugin
{
    public function priority(): int
    {
        return 0;
    }

    public function onInitialize(PluginHost $client): void
    {
    }

    public function onConfigUpdate(ConfigBundle $bundle): void
    {
    }

    public function onBeforeDecision(array $context): array
    {
        return $context;
    }

    public function onDecision(DecisionResult $decision): void
    {
    }

    public function onResolve(array $params): void
    {
    }

    public function onExposure(ExposureEvent $event): bool
    {
        return true;
    }

    public function onTrack(TrackEvent $event): bool
    {
        return true;
    }

    public function onDestroy(): void
    {
    }
}
