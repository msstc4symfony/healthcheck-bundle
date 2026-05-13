<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\DependencyInjection;

use Exception;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Action;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\ActionInterface;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\CachedActionDecorator;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\ParallelAction;
use Override;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

final class HealthCheckExtension extends Extension
{
    public const string ALIAS = 'maxshamaev_healthcheck';

    public const string PARAM_HTTP_CLIENT_TARGETS = 'maxshamaev_healthcheck.http_client_targets';

    public const string PARAM_DEFAULT_TIMEOUT_MS = 'maxshamaev_healthcheck.default_timeout_ms';

    public const string PARAM_TIMEOUT_OVERRIDES = 'maxshamaev_healthcheck.timeout_overrides';

    public const string PARAM_NON_CRITICAL_CHECKERS = 'maxshamaev_healthcheck.non_critical_checkers';

    public const string SERVICE_CACHED_ACTION = 'maxshamaev_healthcheck.action.cached';

    public const string SERVICE_PARALLEL_ACTION = 'maxshamaev_healthcheck.action.parallel';

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
        /** @psalm-suppress ReservedWord */
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

        // Inner runner: sequential Action by default, ParallelAction (fiber-based) when opted in.
        $innerAlias = Action::class;
        if (($config['execution'] ?? 'sequential') === 'parallel') {
            $parallel = new Definition(ParallelAction::class)->setAutowired(true);
            $container->setDefinition(self::SERVICE_PARALLEL_ACTION, $parallel);
            $innerAlias = self::SERVICE_PARALLEL_ACTION;
        }

        if (($config['cache']['enabled'] ?? false) === true) {
            $cached = new Definition(CachedActionDecorator::class)
                ->setAutowired(false)
                ->setArgument(0, new Reference($innerAlias))
                ->setArgument(1, new Reference($config['cache']['pool'] ?? 'cache.app'))
                ->setArgument(2, $config['cache']['ttl_seconds'] ?? 5)
            ;

            $container->setDefinition(self::SERVICE_CACHED_ACTION, $cached);
            $container->setAlias(ActionInterface::class, self::SERVICE_CACHED_ACTION);
        } elseif ($innerAlias !== Action::class) {
            $container->setAlias(ActionInterface::class, $innerAlias);
        }
    }
}
