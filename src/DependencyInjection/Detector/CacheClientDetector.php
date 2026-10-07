<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector;

use Memcache;
use Memcached;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\AbstractReadinessChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\MemcacheChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\MemcachedChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\PredisChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\RedisChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\RedisClusterChecker;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\ServiceClass;
use Override;
use Predis\Client;
use Redis;
use RedisCluster;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Matches the client classes and their subclasses (e.g. SncRedisBundle's phpredis clients).
 */
final readonly class CacheClientDetector implements CheckerDetectorInterface
{
    /**
     * @var array<string, class-string<AbstractReadinessChecker>> client class => checker class
     */
    private const array CHECKERS = [
        Client::class => PredisChecker::class,
        Redis::class => RedisChecker::class,
        RedisCluster::class => RedisClusterChecker::class,
        Memcached::class => MemcachedChecker::class,
        Memcache::class => MemcacheChecker::class,
    ];

    /**
     * @return iterable<string, Definition>
     */
    #[Override]
    public function detect(ContainerBuilder $container): iterable
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition->isAbstract()) {
                continue;
            }

            $checkerClass = $this->checkerClass($container, $definition);
            if ($checkerClass === null) {
                continue;
            }

            yield sprintf('healthcheck.checker.%s', $id) => new Definition($checkerClass)
                    ->addArgument(new Reference($id))
            ;
        }
    }

    /**
     * @return class-string<AbstractReadinessChecker>|null
     */
    private function checkerClass(ContainerBuilder $container, Definition $definition): ?string
    {
        foreach (self::CHECKERS as $clientClass => $checkerClass) {
            if (ServiceClass::is($container, $definition, $clientClass)) {
                return $checkerClass;
            }
        }

        return null;
    }
}
