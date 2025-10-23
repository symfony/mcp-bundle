<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition\Configurator;

return static function (DefinitionConfigurator $configurator): void {
    $configurator->rootNode()
        ->children()
            ->scalarNode('app')->defaultValue('app')->end()
            ->scalarNode('version')->defaultValue('0.0.1')->end()
            ->integerNode('pagination_limit')->defaultValue(50)->end()
            ->scalarNode('instructions')->defaultNull()->end()
            ->arrayNode('client_transports')
                ->children()
                    ->booleanNode('stdio')->defaultFalse()->end()
                    ->booleanNode('http')->defaultFalse()->end()
                ->end()
            ->end()
            ->arrayNode('discovery')
                ->addDefaultsIfNotSet()
                ->children()
                    ->arrayNode('scan_dirs')
                        ->scalarPrototype()->end()
                        ->defaultValue(['src'])
                    ->end()
                    ->arrayNode('exclude_dirs')
                        ->scalarPrototype()->end()
                        ->defaultValue([])
                    ->end()
                ->end()
            ->end()
            ->arrayNode('http')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('path')->defaultValue('/_mcp')->end()
                    ->arrayNode('session')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->enumNode('store')->values(['file', 'memory'])->defaultValue('file')->end()
                            ->scalarNode('directory')->defaultValue('%kernel.cache_dir%/mcp-sessions')->end()
                            ->integerNode('ttl')->min(1)->defaultValue(3600)->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end()
    ;
};
