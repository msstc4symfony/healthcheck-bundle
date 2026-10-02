<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CacheChecker;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\ServiceClass;
use Override;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Probes cache pools that depend on infrastructure (Redis, Memcached, PDO/DBAL, Couchbase, third-party
 * adapters). Pools living in process memory or on the local disk — FrameworkBundle's system pools
 * (cache.system, cache.validator, cache.serializer, …) and a filesystem cache.app — are skipped.
 */
final readonly class CachePoolDetector implements CheckerDetectorInterface
{
    private const array LOCAL_ADAPTERS = [
        ApcuAdapter::class,
        ArrayAdapter::class,
        FilesystemAdapter::class,
        FilesystemTagAwareAdapter::class,
        NullAdapter::class,
        PhpArrayAdapter::class,
        PhpFilesAdapter::class,
    ];

    // FrameworkBundle's "cache.adapter.system" (APCu + PHP files) is declared as AdapterInterface
    // built by this factory, so its class alone does not reveal the adapter.
    private const string SYSTEM_CACHE_FACTORY_METHOD = 'createSystemCache';

    /**
     * @return iterable<string, Definition>
     */
    #[Override]
    public function detect(ContainerBuilder $container): iterable
    {
        /** @var list<array{name?: string}> $tags */
        foreach ($container->findTaggedServiceIds('cache.pool') as $id => $tags) {
            $pool = $container->getDefinition($id);
            if ($pool->isAbstract() || $this->isLocal($container, $pool)) {
                continue;
            }
            $class = $pool->getClass();
            $parentName = null;
            while ($pool instanceof ChildDefinition) {
                $pool = $container->findDefinition($pool->getParent());
                $parentName = $pool->getClass();
                $class ??= $pool->getClass();
                $parentTags = $pool->getTag('cache.pool');
                if ($parentTags !== []) {
                    // Child's keys win, parent fills in the missing ones (e.g. inherited "name").
                    $tags[0] = ($tags[0] ?? []) + $parentTags[0];
                }
            }
            $name = $tags[0]['name'] ?? $id;

            if ($class === null) {
                continue;
            }

            yield sprintf('healthcheck.checker.cache.pool.%s', $name) => new Definition(CacheChecker::class)
                    ->addArgument(new Reference($id))
                    ->addArgument($name)
                    ->addArgument($parentName)
            ;
        }
    }

    private function isLocal(ContainerBuilder $container, Definition $pool): bool
    {
        $class = null;
        $arguments = [];
        for ($definition = $pool;; $definition = $container->findDefinition($definition->getParent())) {
            if ($this->isSystemCacheFactory($definition->getFactory())) {
                return true;
            }
            $class ??= $definition->getClass();
            $arguments += $definition->getArguments();
            if (!$definition instanceof ChildDefinition) {
                break;
            }
        }

        if ($class === null) {
            return false;
        }

        $typed = new Definition($class);
        if (ServiceClass::is($container, $typed, ChainAdapter::class)) {
            return $this->isLocalChain($container, $arguments['index_0'] ?? $arguments[0] ?? null);
        }

        return array_any(
            self::LOCAL_ADAPTERS,
            static fn (string $local): bool => ServiceClass::is($container, $typed, $local),
        );
    }

    /**
     * Before CachePoolPass the chained adapters are service ids; after it, inline child definitions.
     */
    private function isLocalChain(ContainerBuilder $container, mixed $adapters): bool
    {
        if (!is_array($adapters) || $adapters === []) {
            return false;
        }

        foreach ($adapters as $adapter) {
            $definition = match (true) {
                $adapter instanceof Definition => $adapter,
                (is_string($adapter) || $adapter instanceof Reference) && $container->has((string) $adapter) => $container->findDefinition((string) $adapter),
                default => null,
            };
            if (!$definition instanceof Definition || !$this->isLocal($container, $definition)) {
                return false;
            }
        }

        return true;
    }

    private function isSystemCacheFactory(mixed $factory): bool
    {
        return is_array($factory) && ($factory[1] ?? null) === self::SYSTEM_CACHE_FACTORY_METHOD;
    }
}
