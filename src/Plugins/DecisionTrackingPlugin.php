<?php

declare(strict_types=1);

namespace Traffical\Plugins;

use Traffical\Types\DecisionResult;

/**
 * Automatically tracks an exposure whenever a decision is made. Useful when
 * decide() implies the user saw the variant (auto-exposure), avoiding a
 * separate trackExposure() call.
 */
final class DecisionTrackingPlugin extends AbstractPlugin
{
    private ?PluginHost $host = null;

    public function __construct(
        private readonly int $priorityValue = 0,
    ) {
    }

    public function name(): string
    {
        return 'decision-tracking';
    }

    public function priority(): int
    {
        return $this->priorityValue;
    }

    public function onInitialize(PluginHost $client): void
    {
        $this->host = $client;
    }

    public function onDecision(DecisionResult $decision): void
    {
        $this->host?->trackExposure($decision);
    }
}
