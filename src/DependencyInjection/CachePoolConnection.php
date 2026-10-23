<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\DependencyInjection;

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\CouchbaseCollectionAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tells which cache pools a single probe can stand in for. CachePoolPass (which runs before detection)
 * turns the `provider` of the cache.pool tag, a service id or a DSN, into a Reference as the first
 * argument of the adapter: one `.cache_connection.<hash>` service per DSN, so pools on the same DSN
 * share the id.
 *
 * Only adapters whose whole backend is the connection share a key. A tag-aware Redis pool has its own
 * kind: it fails on its own (eviction policy). PDO / DBAL pools are never shared: they fail per table.
 * A pool wired with a named instead of a positional first argument, or through a wrapper such as
 * TagAwareAdapter, is not shared either.
 *
 * @internal
 */
final class CachePoolConnection
{
    // RedisTagAwareAdapter does not extend RedisAdapter, so the kinds never overlap.
    /** @var array<class-string<AdapterInterface>, non-empty-string> */
    private const KINDS = [
        RedisTagAwareAdapter::class => 'redis_tag_aware',
        RedisAdapter::class => 'redis',
        MemcachedAdapter::class => 'memcached',
        CouchbaseCollectionAdapter::class => 'couchbase',
    ];

    /**
     * @return non-empty-string|null "<kind>:<alias-resolved connection service id>"
     */
    public static function shareKey(ContainerBuilder $container, Definition $pool): ?string
    {
        $class = null;
        $connection = null;
        $seen = [];
        for ($definition = $pool;; $definition = $container->findDefinition($parent)) {
            $class ??= $definition->getClass();
            $connection ??= self::positionalZero($definition);
            if (!$definition instanceof ChildDefinition) {
                break;
            }
            $parent = $definition->getParent();
            if (in_array($parent, $seen, true) || !$container->has($parent)) {
                return null;
            }
            $seen[] = $parent;
        }

        $kind = $class === null ? null : self::kindOf($container, $class);
        if ($kind === null || !$connection instanceof Reference) {
            return null;
        }

        $id = self::resolveAlias($container, (string) $connection);

        return $id === null || $id === '' ? null : $kind . ':' . $id;
    }

    /**
     * A child's numeric argument keys mean "append to the parent's" (ResolveChildDefinitionsPass); only
     * `index_N` replaces a position there. A root definition's numeric keys are positions.
     */
    private static function positionalZero(Definition $definition): mixed
    {
        $arguments = $definition->getArguments();

        return $definition instanceof ChildDefinition ? ($arguments['index_0'] ?? null) : ($arguments['index_0'] ?? $arguments[0] ?? null);
    }

    /**
     * @return 'redis_tag_aware'|'redis'|'memcached'|'couchbase'|null
     */
    private static function kindOf(ContainerBuilder $container, string $class): ?string
    {
        $typed = new Definition($class);
        foreach (self::KINDS as $adapter => $kind) {
            if (ServiceClass::is($container, $typed, $adapter)) {
                return $kind;
            }
        }

        return null;
    }

    private static function resolveAlias(ContainerBuilder $container, string $id): ?string
    {
        $seen = [];
        while ($container->hasAlias($id)) {
            if (in_array($id, $seen, true)) {
                return null;
            }
            $seen[] = $id;
            $id = (string) $container->getAlias($id);
        }

        return $id;
    }
}
