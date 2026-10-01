<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\DependencyInjection;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\CheckerDetectorInterface;
use Override;
use RuntimeException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Discovers checker-detector services tagged with `healthcheck.detector`, runs each one,
 * and registers the resulting Definitions as `CheckInterface`-tagged services.
 *
 * Detectors are stateless by contract — the pass instantiates them with `new $class()`
 * and does NOT pass any constructor arguments. Third-party detectors that need state must
 * resolve it lazily inside `detect()` from the ContainerBuilder.
 */
final class HealthCheckerAutoDetectionPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        foreach (array_keys($container->findTaggedServiceIds(CheckerDetectorInterface::TAG)) as $id) {
            $detector = $this->instantiate($container, $id);

            foreach ($detector->detect($container) as $checkerId => $checkerDefinition) {
                if ($this->targetsAbstractService($container, $checkerDefinition)) {
                    continue;
                }

                $this->register($container, $checkerId, $checkerDefinition);
            }
        }
    }

    private function instantiate(ContainerBuilder $container, string $serviceId): CheckerDetectorInterface
    {
        $class = $container->getDefinition($serviceId)->getClass();
        if ($class === null || !is_subclass_of($class, CheckerDetectorInterface::class)) {
            throw new RuntimeException(sprintf('Service "%s" is tagged "%s" but its class is missing or does not implement %s', $serviceId, CheckerDetectorInterface::TAG, CheckerDetectorInterface::class));
        }

        return new $class();
    }

    /**
     * Abstract definitions are templates (e.g. FrameworkBundle's "lock.store.combined.abstract"),
     * not live clients: a checker referencing one fails container compilation.
     */
    private function targetsAbstractService(ContainerBuilder $container, Definition $checker): bool
    {
        return array_any($checker->getArguments(), fn ($argument): bool => $argument instanceof Reference
        && $container->hasDefinition((string) $argument)
        && $container->getDefinition((string) $argument)->isAbstract());
    }

    private function register(ContainerBuilder $container, string $id, Definition $definition): void
    {
        $definition->setAutowired(true);
        $definition->addTag(CheckInterface::class);

        $container->setDefinition($id, $definition);
    }
}
