<?php

declare(strict_types=1);

namespace Traffical\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Traffical\ClientOptions;

/**
 * Validates and normalizes the `traffical` configuration tree.
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('traffical');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('org_id')->defaultValue('')->end()
                ->scalarNode('project_id')->defaultValue('')->end()
                ->scalarNode('env')->defaultValue('production')->end()
                ->scalarNode('api_key')->defaultValue('')->end()
                ->scalarNode('base_url')->defaultValue(ClientOptions::DEFAULT_BASE_URL)->end()
                ->enumNode('evaluation_mode')
                    ->values(['bundle', 'server'])
                    ->defaultValue('bundle')
                ->end()
                ->booleanNode('disable_cloud_events')->defaultFalse()->end()
                ->booleanNode('deduplicate_assignment_logger')->defaultTrue()->end()
            ->end();

        return $treeBuilder;
    }
}
