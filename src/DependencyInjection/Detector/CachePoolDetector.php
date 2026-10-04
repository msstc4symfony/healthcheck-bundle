<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CacheChecker;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\CachePoolLocality;
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

            yield sprintf('healthcheck.checker.cache.pool.%s', $name) => new Definition(CacheChecker::class)
                    ->addArgument(new Reference($id))
                    ->addArgument($name)
                    ->addArgument($parentName)
            ;
        }
    }

    private function poolName(Definition $pool): ?string
    {
        $attributes = $pool->getTag('cache.pool')[0] ?? null;
        $name = is_array($attributes) ? $attributes['name'] ?? null : null;

        return is_string($name) ? $name : null;
    }
}
