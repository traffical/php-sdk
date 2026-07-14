<?php

declare(strict_types=1);

namespace Traffical\Plugins;

use Traffical\TrackOptions;
use Traffical\Types\DecisionResult;

/**
 * Minimal client surface exposed to plugins via onInitialize. Avoids a hard
 * dependency on the concrete client class. {@see \Traffical\Client} implements
 * this.
 */
interface PluginHost
{
    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $defaults
     */
    public function decide(array $context, array $defaults): DecisionResult;

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    public function getParams(array $context, array $defaults): array;

    /**
     * @param array<string, mixed>|null $properties
     */
    public function track(string $event, ?array $properties = null, ?TrackOptions $options = null): void;

    public function trackExposure(DecisionResult $decision): void;

    public function getConfigVersion(): ?string;
}
