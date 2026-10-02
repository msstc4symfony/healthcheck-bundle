<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\DependencyInjection;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\CheckerDetectorInterface;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\HttpClientTargetDetector;
use Override;
use RuntimeException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Lock\Store\StoreFactory;

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
        $detected = [];
        foreach (array_keys($container->findTaggedServiceIds(CheckerDetectorInterface::TAG)) as $id) {
            $detector = $this->instantiate($container, $id);

            foreach ($detector->detect($container) as $checkerId => $checkerDefinition) {
                // Explicitly configured targets must still fail loudly on a bad reference.
                if (!$detector instanceof HttpClientTargetDetector && $this->targetsAbstractService($container, $checkerDefinition)) {
                    continue;
                }

                $detected[$checkerId] = $checkerDefinition;
            }
        }

        $probedTargets = [];
        foreach ($detected as $checkerDefinition) {
            $target = $this->target($checkerDefinition);
            if ($target !== null) {
                $probedTargets[$this->resolveAlias($container, $target)] = true;
            }
        }

        foreach ($detected as $checkerId => $checkerDefinition) {
            if (!$this->wrapsProbedConnection($container, $checkerDefinition, $probedTargets)) {
                $this->register($container, $checkerId, $checkerDefinition);
            }
        }
    }

    /**
     * framework.lock given a connection service id (a \Redis or DBAL connection, …) builds a hidden
     * StoreFactory store around it; the connection already has its own probe.
     *
     * @param array<string, true> $probedTargets
     */
    private function wrapsProbedConnection(ContainerBuilder $container, Definition $checker, array $probedTargets): bool
    {
        $store = $this->target($checker);
        if ($store === null || !$container->has($store)) {
            return false;
        }

        $storeDefinition = $container->findDefinition($store);
        $factory = $storeDefinition->getFactory();
        if (!is_array($factory) || $factory[0] !== StoreFactory::class || $factory[1] !== 'createStore') {
            return false;
        }

        $connection = $storeDefinition->getArguments()[0] ?? null;

        return $connection instanceof Reference && isset($probedTargets[$this->resolveAlias($container, (string) $connection)]);
    }

    private function target(Definition $checker): ?string
    {
        $target = $checker->getArguments()[0] ?? null;

        return $target instanceof Reference ? (string) $target : null;
    }

    private function resolveAlias(ContainerBuilder $container, string $id): string
    {
        while ($container->hasAlias($id)) {
            $id = (string) $container->getAlias($id);
        }

        return $id;
    }

    private function instantiate(ContainerBuilder $container, string $serviceId): CheckerDetectorInterface
    {
        $definition = $container->getDefinition($serviceId);
        $class = $definition->getClass();
        $detector = $class !== null && ServiceClass::is($container, $definition, CheckerDetectorInterface::class) ? new $class() : null;
        if (!$detector instanceof CheckerDetectorInterface) {
            throw new RuntimeException(sprintf('Service "%s" is tagged "%s" but its class is missing or does not implement %s', $serviceId, CheckerDetectorInterface::TAG, CheckerDetectorInterface::class));
        }

        return $detector;
    }

    /**
     * Abstract definitions are templates (e.g. FrameworkBundle's "lock.store.combined.abstract"),
     * not live clients: a checker referencing one fails container compilation.
     */
    private function targetsAbstractService(ContainerBuilder $container, Definition $checker): bool
    {
        return array_any(
            $checker->getArguments(),
            fn (mixed $argument): bool => $this->isAbstractReference($container, $argument),
        );
    }

    private function isAbstractReference(ContainerBuilder $container, mixed $argument): bool
    {
        return $argument instanceof Reference
            && $container->has((string) $argument)
            && $container->findDefinition((string) $argument)->isAbstract();
    }

    private function register(ContainerBuilder $container, string $id, Definition $definition): void
    {
        $definition->setAutowired(true);
        $definition->addTag(CheckInterface::class);

        $container->setDefinition($id, $definition);
    }
}
