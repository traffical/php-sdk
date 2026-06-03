<?php

declare(strict_types=1);

namespace Traffical\Laravel;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Traffical\Client;
use Traffical\ClientOptions;

/**
 * Laravel integration. Registers the Traffical {@see Client} as a shared
 * singleton built from `config/traffical.php`, aliased as `traffical` for the
 * {@see Traffical} facade.
 *
 * Auto-discovered via composer `extra.laravel.providers`; no manual wiring
 * needed on Laravel 5.5+.
 */
final class TrafficalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/traffical.php', 'traffical');

        $this->app->singleton(Client::class, static function (Container $app): Client {
            /** @var array<string, mixed> $config */
            $config = $app->make('config')->get('traffical', []);

            $logger = $app->bound(LoggerInterface::class)
                ? $app->make(LoggerInterface::class)
                : null;
            $cache = $app->bound(CacheInterface::class)
                ? $app->make(CacheInterface::class)
                : null;

            $options = new ClientOptions(
                orgId: (string) ($config['org_id'] ?? ''),
                projectId: (string) ($config['project_id'] ?? ''),
                env: (string) ($config['env'] ?? 'production'),
                apiKey: (string) ($config['api_key'] ?? ''),
                baseUrl: (string) ($config['base_url'] ?? ClientOptions::DEFAULT_BASE_URL),
                evaluationMode: (string) ($config['evaluation_mode'] ?? 'bundle'),
                disableCloudEvents: (bool) ($config['disable_cloud_events'] ?? false),
                deduplicateAssignmentLogger: (bool) ($config['deduplicate_assignment_logger'] ?? true),
                logger: $logger,
                cache: $cache,
            );

            return new Client($options);
        });

        $this->app->alias(Client::class, 'traffical');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/traffical.php' => $this->app->configPath('traffical.php'),
            ], 'traffical-config');
        }
    }

    /**
     * @return list<string>
     */
    public function provides(): array
    {
        return [Client::class, 'traffical'];
    }
}
