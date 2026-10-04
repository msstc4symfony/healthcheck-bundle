<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\LockStoreChecker;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\ServiceClass;
use Override;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\StoreFactory;

/**
 * Probes the stores the application uses: every store FrameworkBundle builds for a configured
 * `framework.lock` resource carries the "lock.store" tag, and stores the application registers
 * itself have public-style ids. Untagged hidden stores are FrameworkBundle internals, such as the
 * ".lock.flock.store" / ".lock.semaphore.store" predefined since 8.1 whether used or not.
 *
 * Framework stores are named after the resources using them ("lock.default", "lock.invoice[1]" in a
 * combined store), sorted: their service ids end in a hash of the DSN, which may also carry
 * credentials and changes with it. The checker id joins the names with "+"
 * ("healthcheck.checker.lock.default+lock.reports") so non_critical / timeouts keys stay stable;
 * the label joins them with ", ". A store no resource uses keeps its service id.
 */
final readonly class LockStoreDetector implements CheckerDetectorInterface, WrappedTargetDetectorInterface
{
    private const string FRAMEWORK_STORE_TAG = 'lock.store';

    private const string FRAMEWORK_FACTORY_TEMPLATE = 'lock.factory.abstract';

    private const string FRAMEWORK_COMBINED_STORE_TEMPLATE = 'lock.store.combined.abstract';

    /**
     * @return iterable<string, Definition>
     */
    #[Override]
    public function detect(ContainerBuilder $container): iterable
    {
        $resourceLabels = $this->resourceLabels($container);

        foreach ($container->getDefinitions() as $id => $definition) {
            if (str_starts_with($id, '.') && !$definition->hasTag(self::FRAMEWORK_STORE_TAG)) {
                continue;
            }

            if (!ServiceClass::is($container, $definition, PersistingStoreInterface::class)) {
                continue;
            }

            $labels = $resourceLabels[$id] ?? [$id];
            sort($labels);

            yield sprintf('healthcheck.checker.%s', implode('+', $labels)) => new Definition(LockStoreChecker::class)
                ->addArgument(new Reference($id))
                ->addArgument(implode(', ', $labels))
            ;
        }
    }

    /**
     * framework.lock given a connection service id (a \Redis or DBAL connection, …) builds a hidden
     * StoreFactory store around it.
     */
    #[Override]
    public function wrappedTarget(ContainerBuilder $container, Definition $checker): ?string
    {
        $store = $this->firstReference($checker);
        if (!$store instanceof Reference || !$container->has((string) $store)) {
            return null;
        }

        // Compared part by part: Rector turns an [X::class, 'method'] literal into a first-class callable.
        $storeDefinition = $container->findDefinition((string) $store);
        $factory = $storeDefinition->getFactory();
        if (!is_array($factory) || $factory[0] !== StoreFactory::class || $factory[1] !== 'createStore') {
            return null;
        }

        $connection = $this->firstReference($storeDefinition);

        return $connection instanceof Reference ? (string) $connection : null;
    }

    /**
     * @return array<string, non-empty-list<string>> store id => labels of the resources using it
     */
    private function resourceLabels(ContainerBuilder $container): array
    {
        $labels = [];
        foreach ($container->getDefinitions() as $id => $definition) {
            if (preg_match('/^lock\.(.+)\.factory$/', $id, $match) !== 1 || !$this->isChildOf($definition, self::FRAMEWORK_FACTORY_TEMPLATE)) {
                continue;
            }

            $resource = 'lock.' . $match[1];
            $store = $this->firstReference($definition);
            if (!$store instanceof Reference) {
                continue;
            }

            $storeDefinition = $container->hasDefinition((string) $store) ? $container->getDefinition((string) $store) : null;
            $members = $storeDefinition instanceof Definition && $this->isChildOf($storeDefinition, self::FRAMEWORK_COMBINED_STORE_TEMPLATE)
                ? $this->firstReferenceList($storeDefinition)
                : [];

            if ($members === []) {
                $labels[(string) $store][] = $resource;

                continue;
            }

            foreach ($members as $position => $member) {
                $labels[(string) $member][] = sprintf('%s[%d]', $resource, $position);
            }
        }

        return $labels;
    }

    private function isChildOf(Definition $definition, string $parent): bool
    {
        return $definition instanceof ChildDefinition && $definition->getParent() === $parent;
    }

    private function firstReference(Definition $definition): ?Reference
    {
        $arguments = $definition->getArguments();
        $argument = $arguments['index_0'] ?? $arguments[0] ?? null;

        return $argument instanceof Reference ? $argument : null;
    }

    /**
     * @return array<non-negative-int, Reference> position in the list => reference
     */
    private function firstReferenceList(Definition $definition): array
    {
        $arguments = $definition->getArguments();
        $argument = $arguments['index_0'] ?? $arguments[0] ?? null;

        return is_array($argument)
            ? array_filter(array_values($argument), static fn (mixed $member): bool => $member instanceof Reference)
            : [];
    }
}
