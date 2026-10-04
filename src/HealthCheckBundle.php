<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle;

use LogicException;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Action;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\ActionInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\CachedActionDecorator;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\ParallelAction;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\ContainerIds;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\HealthCheckerAutoDetectionPass;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\HealthCheckerCriticalityDecorationPass;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\HealthCheckerDeferredConstructionPass;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\HealthCheckerTimeoutDecorationPass;
use Override;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class HealthCheckBundle extends AbstractBundle
{
    protected string $extensionAlias = ContainerIds::ALIAS;

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Detectors discover themselves via the `healthcheck.detector` tag (autoconfigured by
        // CheckerDetectorInterface). Third-party bundles can contribute detectors by registering
        // services that implement the interface — no need to subclass HealthCheckBundle.
        $container->addCompilerPass(new HealthCheckerAutoDetectionPass());

        // Order matters: Timeout MUST wrap first so the outermost decorator is Criticality.
        // Final composition = NonCritical( Deferred( Timeout( inner ) ) ). A non-critical checker
        // that exceeds its budget or cannot be constructed then produces a warning, not an error.
        $container->addCompilerPass(new HealthCheckerTimeoutDecorationPass());
        $container->addCompilerPass(new HealthCheckerDeferredConstructionPass());
        $container->addCompilerPass(new HealthCheckerCriticalityDecorationPass());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
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
    }

    /**
     * @param array<array-key, mixed> $config processed by configure()
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(__DIR__ . '/Resources/config/services.php');

        $timeouts = $this->section($config, 'timeouts');
        $container->parameters()
            ->set(ContainerIds::PARAM_HTTP_CLIENT_TARGETS, $config['http_client'])
            ->set(ContainerIds::PARAM_DEFAULT_TIMEOUT_MS, $timeouts['default_ms'])
            ->set(ContainerIds::PARAM_TIMEOUT_OVERRIDES, $timeouts['overrides'])
            ->set(ContainerIds::PARAM_NON_CRITICAL_CHECKERS, $config['non_critical'])
        ;

        $this->wireActionPipeline($builder, $config['execution'] === 'parallel', $this->section($config, 'cache'));
    }

    /**
     * @param array<array-key, mixed> $config
     *
     * @return array<array-key, mixed>
     */
    private function section(array $config, string $key): array
    {
        $section = $config[$key];
        if (!is_array($section)) {
            throw new LogicException(sprintf('The "%s.%s" configuration is not an array.', ContainerIds::ALIAS, $key));
        }

        return $section;
    }

    /**
     * @param array<array-key, mixed> $cache
     */
    private function wireActionPipeline(ContainerBuilder $container, bool $parallel, array $cache): void
    {
        $innerServiceId = Action::class;
        if ($parallel) {
            $container->setDefinition(ContainerIds::SERVICE_PARALLEL_ACTION, new Definition(ParallelAction::class)->setAutowired(true));
            $innerServiceId = ContainerIds::SERVICE_PARALLEL_ACTION;

            // Dropped so that introspection ("debug:container Action") reflects the active wiring.
            $container->removeDefinition(Action::class);
        }

        $actionInterfaceTarget = $innerServiceId;

        if ($cache['enabled'] === true) {
            $pool = $cache['pool'];
            if (!is_string($pool)) {
                throw new LogicException(sprintf('The "%s.cache.pool" option must be a service id.', ContainerIds::ALIAS));
            }

            $container->setDefinition(ContainerIds::SERVICE_CACHED_ACTION, new Definition(CachedActionDecorator::class)
                ->setArgument(0, new Reference($innerServiceId))
                ->setArgument(1, new Reference($pool))
                ->setArgument(2, $cache['ttl_seconds']));
            $actionInterfaceTarget = ContainerIds::SERVICE_CACHED_ACTION;
        }

        $container->setAlias(ActionInterface::class, $actionInterfaceTarget);
    }
}
