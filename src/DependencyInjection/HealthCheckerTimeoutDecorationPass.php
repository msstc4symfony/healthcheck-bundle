<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\DependencyInjection;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\TimeoutCheckerDecorator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Wraps every service tagged CheckInterface::class in a TimeoutCheckerDecorator,
 * applying per-checker overrides on top of the configured default budget.
 *
 * Must run AFTER HealthCheckerAutoDetectionPass.
 */
final readonly class HealthCheckerTimeoutDecorationPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(HealthCheckExtension::PARAM_DEFAULT_TIMEOUT_MS)) {
            return;
        }

        $defaultTimeout = (int) $container->getParameter(HealthCheckExtension::PARAM_DEFAULT_TIMEOUT_MS);

        /** @var array<string, int> $overrides */
        $overrides = (array) $container->getParameter(HealthCheckExtension::PARAM_TIMEOUT_OVERRIDES);

        foreach (array_keys($container->findTaggedServiceIds(CheckInterface::class)) as $id) {
            $inner = $container->getDefinition($id);

            // Skip already-wrapped definitions (idempotency safeguard).
            if ($inner->getClass() === TimeoutCheckerDecorator::class) {
                continue;
            }

            $inner->clearTag(CheckInterface::class);
            $innerId = $id . '.inner';
            $container->setDefinition($innerId, $inner);
            $container->removeDefinition($id);

            $timeout = $overrides[$id] ?? $defaultTimeout;

            $decorator = new Definition(TimeoutCheckerDecorator::class)
                ->setAutowired(true)
                ->addArgument(new Reference($innerId))
                ->addArgument($timeout)
                ->addTag(CheckInterface::class)
            ;

            $container->setDefinition($id, $decorator);
        }
    }
}
