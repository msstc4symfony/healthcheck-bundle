<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final readonly class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('maxshamaev_healthcheck');

        $treeBuilder->getRootNode()
            ->children()
                ->enumNode('execution')
                    ->info('Sequential (default) or parallel (Fiber-based; only beneficial with async-aware checker I/O).')
                    ->values(['sequential', 'parallel'])
                    ->defaultValue('sequential')
                ->end()
                ->arrayNode('cache')
                    ->info('PSR-6 cache layer that memoizes Action::run() results for the configured TTL.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->integerNode('ttl_seconds')->defaultValue(5)->min(1)->end()
                        ->scalarNode('pool')
                            ->info('Service id of the PSR-6 cache pool (CacheItemPoolInterface).')
                            ->defaultValue('cache.app')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('non_critical')
                    ->info('Service ids of checkers whose failures become warnings instead of errors (do not fail readiness).')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('timeouts')
                    ->info('Per-checker wall-clock budgets. The decorator reports an error if exceeded.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('default_ms')->defaultValue(2000)->min(1)->end()
                        ->arrayNode('overrides')
                            ->info('Service id -> timeout (ms); e.g. "healthcheck.checker.doctrine.dbal.default_connection: 5000"')
                            ->useAttributeAsKey('name')
                            ->integerPrototype()->min(1)->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('http_client')
                    ->info('Configurable HTTP probes (opt-in; not auto-detected).')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('url')
                                ->isRequired()
                                ->cannotBeEmpty()
                                ->validate()
                                    ->ifTrue(static fn (string $url): bool => !str_starts_with($url, 'http://') && !str_starts_with($url, 'https://'))
                                    ->thenInvalid('http_client.url must use http:// or https:// scheme; got %s')
                                ->end()
                            ->end()
                            ->scalarNode('method')->defaultValue('GET')->end()
                            ->arrayNode('expected_status_codes')
                                ->integerPrototype()->end()
                                ->defaultValue([200, 204])
                            ->end()
                            ->scalarNode('client')
                                ->info('Service id of HttpClientInterface to use; defaults to autowired client.')
                                ->defaultNull()
                            ->end()
                            ->integerNode('timeout_seconds')->defaultValue(3)->min(1)->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
