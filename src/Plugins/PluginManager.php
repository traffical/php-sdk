<?php

declare(strict_types=1);

namespace Traffical\Plugins;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use Traffical\Types\ConfigBundle;
use Traffical\Types\DecisionResult;
use Traffical\Types\ExposureEvent;
use Traffical\Types\TrackEvent;

/**
 * Manages plugin lifecycle and hook dispatch. Registration dedupes by name and
 * orders by priority (descending). Hook errors are caught and logged so a
 * misbehaving plugin never breaks evaluation or event delivery.
 */
final class PluginManager
{
    /** @var list<Plugin> */
    private array $plugins = [];

    private readonly LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Registers a plugin. Returns false if a plugin with the same name already
     * exists.
     */
    public function register(Plugin $plugin): bool
    {
        foreach ($this->plugins as $existing) {
            if ($existing->name() === $plugin->name()) {
                $this->logger->warning('[Traffical] Plugin already registered, skipping', ['name' => $plugin->name()]);

                return false;
            }
        }

        $this->plugins[] = $plugin;
        usort($this->plugins, static fn (Plugin $a, Plugin $b): int => $b->priority() <=> $a->priority());

        return true;
    }

    public function unregister(string $name): bool
    {
        foreach ($this->plugins as $i => $plugin) {
            if ($plugin->name() === $name) {
                array_splice($this->plugins, $i, 1);

                return true;
            }
        }

        return false;
    }

    public function get(string $name): ?Plugin
    {
        foreach ($this->plugins as $plugin) {
            if ($plugin->name() === $name) {
                return $plugin;
            }
        }

        return null;
    }

    /**
     * @return list<Plugin>
     */
    public function getAll(): array
    {
        return $this->plugins;
    }

    public function clear(): void
    {
        $this->plugins = [];
    }

    public function runInitialize(PluginHost $client): void
    {
        foreach ($this->plugins as $plugin) {
            $this->guard($plugin, 'onInitialize', fn () => $plugin->onInitialize($client));
        }
    }

    public function runConfigUpdate(ConfigBundle $bundle): void
    {
        foreach ($this->plugins as $plugin) {
            $this->guard($plugin, 'onConfigUpdate', fn () => $plugin->onConfigUpdate($bundle));
        }
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function runBeforeDecision(array $context): array
    {
        foreach ($this->plugins as $plugin) {
            try {
                $context = $plugin->onBeforeDecision($context);
            } catch (Throwable $e) {
                $this->logError($plugin, 'onBeforeDecision', $e);
            }
        }

        return $context;
    }

    public function runDecision(DecisionResult $decision): void
    {
        foreach ($this->plugins as $plugin) {
            $this->guard($plugin, 'onDecision', fn () => $plugin->onDecision($decision));
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public function runResolve(array $params): void
    {
        foreach ($this->plugins as $plugin) {
            $this->guard($plugin, 'onResolve', fn () => $plugin->onResolve($params));
        }
    }

    /**
     * Returns false if any plugin cancels the exposure.
     */
    public function runExposure(ExposureEvent $event): bool
    {
        foreach ($this->plugins as $plugin) {
            try {
                if ($plugin->onExposure($event) === false) {
                    return false;
                }
            } catch (Throwable $e) {
                $this->logError($plugin, 'onExposure', $e);
            }
        }

        return true;
    }

    /**
     * Returns false if any plugin cancels the track event.
     */
    public function runTrack(TrackEvent $event): bool
    {
        foreach ($this->plugins as $plugin) {
            try {
                if ($plugin->onTrack($event) === false) {
                    return false;
                }
            } catch (Throwable $e) {
                $this->logError($plugin, 'onTrack', $e);
            }
        }

        return true;
    }

    public function runDestroy(): void
    {
        foreach ($this->plugins as $plugin) {
            $this->guard($plugin, 'onDestroy', fn () => $plugin->onDestroy());
        }
    }

    private function guard(Plugin $plugin, string $hook, callable $fn): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            $this->logError($plugin, $hook, $e);
        }
    }

    private function logError(Plugin $plugin, string $hook, Throwable $e): void
    {
        $this->logger->warning('[Traffical] Plugin hook error', [
            'plugin' => $plugin->name(),
            'hook' => $hook,
            'error' => $e->getMessage(),
        ]);
    }
}
