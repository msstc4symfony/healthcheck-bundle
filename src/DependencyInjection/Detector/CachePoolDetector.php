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
        /** @var list<array{name?: string}> $tags */
        foreach ($container->findTaggedServiceIds('cache.pool') as $id => $tags) {
            $pool = $container->getDefinition($id);
            if ($pool->isAbstract() || CachePoolLocality::isLocal($container, $pool)) {
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
}
