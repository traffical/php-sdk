<?php

declare(strict_types=1);

namespace Traffical\Plugins;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Traffical\Types\ConfigBundle;
use Traffical\Types\DecisionResult;
use Traffical\Types\ExposureEvent;
use Traffical\Types\TrackEvent;

/**
 * Verbose logging + in-memory inspection of SDK activity. Useful for local
 * development and tests: logs every lifecycle hook via PSR-3 and records a
 * bounded history that can be queried with {@see self::getEvents()}.
 */
final class DebugPlugin extends AbstractPlugin
{
    private readonly LoggerInterface $logger;

    /** @var list<array{hook: string, data: mixed}> */
    private array $events = [];

    public function __construct(
        ?LoggerInterface $logger = null,
        private readonly int $maxEvents = 100,
        private readonly int $priorityValue = 1000,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function name(): string
    {
        return 'debug';
    }

    public function priority(): int
    {
        return $this->priorityValue;
    }

    public function onConfigUpdate(ConfigBundle $bundle): void
    {
        $this->record('onConfigUpdate', ['version' => $bundle->version]);
    }

    public function onDecision(DecisionResult $decision): void
    {
        $this->record('onDecision', $decision->toArray());
    }

    public function onResolve(array $params): void
    {
        $this->record('onResolve', $params);
    }

    public function onExposure(ExposureEvent $event): bool
    {
        $this->record('onExposure', $event->toArray());

        return true;
    }

    public function onTrack(TrackEvent $event): bool
    {
        $this->record('onTrack', $event->toArray());

        return true;
    }

    /**
     * @return list<array{hook: string, data: mixed}>
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    public function clear(): void
    {
        $this->events = [];
    }

    private function record(string $hook, mixed $data): void
    {
        $this->logger->debug('[Traffical] ' . $hook, ['data' => $data]);
        $this->events[] = ['hook' => $hook, 'data' => $data];
        if (count($this->events) > $this->maxEvents) {
            array_shift($this->events);
        }
    }
}
