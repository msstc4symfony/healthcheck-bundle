<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\DependencyInjection;

use Exception;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Action;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\ActionInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\CachedActionDecorator;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\ParallelAction;
use Override;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

final class HealthCheckExtension extends Extension
{
    public const string ALIAS = 'msstc4symfony_healthcheck';

    public const string PARAM_HTTP_CLIENT_TARGETS = 'msstc4symfony_healthcheck.http_client_targets';

    public const string PARAM_DEFAULT_TIMEOUT_MS = 'msstc4symfony_healthcheck.default_timeout_ms';

    public const string PARAM_TIMEOUT_OVERRIDES = 'msstc4symfony_healthcheck.timeout_overrides';

    public const string PARAM_NON_CRITICAL_CHECKERS = 'msstc4symfony_healthcheck.non_critical_checkers';

    public const string SERVICE_CACHED_ACTION = 'msstc4symfony_healthcheck.action.cached';

    public const string SERVICE_PARALLEL_ACTION = 'msstc4symfony_healthcheck.action.parallel';

    #[Override]
    public function getAlias(): string
    {
        return self::ALIAS;
    }

    /**
     * @param array<array-key, mixed> $configs
     *
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        /** @var array{
         *     execution?: string,
         *     non_critical?: list<string>,
         *     timeouts?: array{default_ms: int, overrides: array<string, int>},
         *     http_client?: array<string, array{url: string, method: string, expected_status_codes: list<int>, client: string|null, timeout_seconds: int}>,
         *     cache?: array{enabled: bool, ttl_seconds: int, pool: string},
         * } $config
         */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter(self::PARAM_HTTP_CLIENT_TARGETS, $config['http_client'] ?? []);
        $container->setParameter(self::PARAM_DEFAULT_TIMEOUT_MS, $config['timeouts']['default_ms'] ?? 2000);
        $container->setParameter(self::PARAM_TIMEOUT_OVERRIDES, $config['timeouts']['overrides'] ?? []);
        $container->setParameter(self::PARAM_NON_CRITICAL_CHECKERS, $config['non_critical'] ?? []);

        $this->wireActionPipeline($container, $config);
    }

    /**
     * @param array{
     *     execution?: string,
     *     cache?: array{enabled: bool, ttl_seconds: int, pool: string},
     *     ...
     * } $config
     */
    private function wireActionPipeline(ContainerBuilder $container, array $config): void
    {
        // Inner runner: sequential Action by default, ParallelAction (fiber-based) when opted in.
        $innerServiceId = Action::class;
        if (($config['execution'] ?? 'sequential') === 'parallel') {
            $parallel = new Definition(ParallelAction::class)->setAutowired(true);
            $container->setDefinition(self::SERVICE_PARALLEL_ACTION, $parallel);
            $innerServiceId = self::SERVICE_PARALLEL_ACTION;

            // Action would otherwise stay in the container as a dead-but-resolvable service.
            // Drop it so introspection ("debug:container Action") reflects the active wiring.
            if ($container->hasDefinition(Action::class)) {
                $container->removeDefinition(Action::class);
            }
        }

        $actionInterfaceTarget = $innerServiceId;

        if (($config['cache']['enabled'] ?? false) === true) {
            $cached = new Definition(CachedActionDecorator::class)
                ->setAutowired(false)
                ->setArgument(0, new Reference($innerServiceId))
                ->setArgument(1, new Reference($config['cache']['pool'] ?? 'cache.app'))
                ->setArgument(2, $config['cache']['ttl_seconds'] ?? 5)
            ;

            $container->setDefinition(self::SERVICE_CACHED_ACTION, $cached);
            $actionInterfaceTarget = self::SERVICE_CACHED_ACTION;
        }

        // Always alias ActionInterface explicitly — do not rely on the PSR-4 service loader.
        $container->setAlias(ActionInterface::class, $actionInterfaceTarget);
    }
}
