<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\DependencyInjection;

use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use MSSTC4PHP\HealthCheckBundle\DependencyInjection\Detector\CheckerDetectorInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

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
    public function process(ContainerBuilder $container): void
    {
        foreach (array_keys($container->findTaggedServiceIds(CheckerDetectorInterface::TAG)) as $id) {
            $detector = $this->instantiate($container, $id);

            foreach ($detector->detect($container) as $checkerId => $checkerDefinition) {
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

    private function register(ContainerBuilder $container, string $id, Definition $definition): void
    {
        $definition->setAutowired(true);
        $definition->addTag(CheckInterface::class);

        $container->setDefinition($id, $definition);
    }
}
