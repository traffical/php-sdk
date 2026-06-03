<?php

declare(strict_types=1);

namespace Traffical\Symfony\DependencyInjection;

use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Traffical\Client;
use Traffical\ClientOptions;

/**
 * Builds the `Traffical\Client` service (and a `traffical.client` alias) from
 * the validated configuration. The client is autowirable by its FQCN.
 */
final class TrafficalExtension extends Extension
{
    /**
     * @param array<array-key, mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $options = new Definition(ClientOptions::class);
        $options->setArguments([
            '$orgId' => (string) $config['org_id'],
            '$projectId' => (string) $config['project_id'],
            '$env' => (string) $config['env'],
            '$apiKey' => (string) $config['api_key'],
            '$baseUrl' => (string) $config['base_url'],
            '$evaluationMode' => (string) $config['evaluation_mode'],
            '$disableCloudEvents' => (bool) $config['disable_cloud_events'],
            '$deduplicateAssignmentLogger' => (bool) $config['deduplicate_assignment_logger'],
            '$logger' => new Reference(
                LoggerInterface::class,
                ContainerBuilder::NULL_ON_INVALID_REFERENCE,
            ),
            '$cache' => new Reference(
                CacheInterface::class,
                ContainerBuilder::NULL_ON_INVALID_REFERENCE,
            ),
        ]);
        $options->setPublic(false);
        $container->setDefinition(ClientOptions::class, $options);

        $client = new Definition(Client::class, [new Reference(ClientOptions::class)]);
        $client->setPublic(true);
        $container->setDefinition(Client::class, $client);
        $container->setAlias('traffical.client', Client::class)->setPublic(true);
    }

    public function getAlias(): string
    {
        return 'traffical';
    }
}
