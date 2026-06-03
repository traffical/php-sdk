<?php

declare(strict_types=1);

namespace Traffical\Laravel;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for the Traffical {@see \Traffical\Client} singleton.
 *
 * @method static array<string, mixed> getParams(array<string, mixed> $context, array<string, mixed> $defaults)
 * @method static \Traffical\Types\DecisionResult decide(array<string, mixed> $context, array<string, mixed> $defaults)
 * @method static void trackExposure(\Traffical\Types\DecisionResult $decision)
 * @method static void track(string $event, ?array<string, mixed> $properties = null, ?string $decisionId = null, ?string $unitKey = null)
 * @method static void flushEvents()
 * @method static void refreshConfig()
 * @method static ?string getConfigVersion()
 *
 * @see \Traffical\Client
 */
final class Traffical extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'traffical';
    }
}
