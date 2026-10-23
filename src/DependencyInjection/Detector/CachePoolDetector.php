<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector;

use Msstc4Symfony\HealthCheckBundle\DependencyInjection\CachePoolConnection;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\CachePoolLocality;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\ContainerIds;
use Msstc4Symfony\HealthCheckBundle\Infrastructure\Checker\CacheChecker;
use Override;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Probes cache pools that depend on infrastructure (Redis, Memcached, PDO/DBAL, Couchbase, third-party
 * adapters). Pools living in process memory or on the local disk — FrameworkBundle's system pools
 * (cache.system, cache.validator, cache.serializer, …) and a filesystem cache.app — are skipped
 * (see CachePoolLocality).
 *
 * Every pool keeps its own checker (stable ids). Pools whose whole backend is one shared connection
 * carry a share key (see CachePoolConnection), so within one probe run only the first of them really
 * probes the connection.
 *
 * @internal
 */
final readonly class CachePoolDetector implements CheckerDetectorInterface
{
    /**
     * @return iterable<string, Definition>
     */
    #[Override]
    public function detect(ContainerBuilder $container): iterable
    {
        foreach (array_keys($container->findTaggedServiceIds('cache.pool')) as $id) {
            $pool = $container->getDefinition($id);
            if ($pool->isAbstract() || CachePoolLocality::isLocal($container, $pool)) {
                continue;
            }
            $class = $pool->getClass();
            // The child's own "name" wins; a parent fills it in when the child has none.
            $name = $this->poolName($pool);
            $shareKey = CachePoolConnection::shareKey($container, $pool);
            $parentName = null;
            while ($pool instanceof ChildDefinition) {
                $pool = $container->findDefinition($pool->getParent());
                $parentName = $pool->getClass();
                $class ??= $pool->getClass();
                $name ??= $this->poolName($pool);
            }
            $name ??= $id;

            if ($class === null) {
                continue;
            }

            $checker = (new Definition(CacheChecker::class))
                ->addArgument(new Reference($id))
                ->addArgument($name)
                ->addArgument($parentName)
                ->addArgument($shareKey)
            ;
            if ($shareKey !== null) {
                $checker->addTag(ContainerIds::TAG_SHARED_PROBE, ['key' => $shareKey]);
            }

            yield sprintf('healthcheck.checker.cache.pool.%s', $name) => $checker;
        }
    }

    private function poolName(Definition $pool): ?string
    {
        $attributes = $pool->getTag('cache.pool')[0] ?? null;
        $name = is_array($attributes) ? $attributes['name'] ?? null : null;

        return is_string($name) ? $name : null;
    }
}
