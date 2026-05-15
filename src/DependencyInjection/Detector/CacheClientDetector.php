<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\DependencyInjection\Detector;

use Memcache;
use Memcached;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\MemcacheChecker;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\MemcachedChecker;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\PredisChecker;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\RedisChecker;
use Predis\Client;
use Redis;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final readonly class CacheClientDetector implements CheckerDetectorInterface
{
    /**
     * @return iterable<string, Definition>
     */
    public function detect(ContainerBuilder $container): iterable
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();
            if ($class === null) {
                continue;
            }

            $checkerClass = match ($class) {
                Client::class => PredisChecker::class,
                Memcached::class => MemcachedChecker::class,
                Memcache::class => MemcacheChecker::class,
                Redis::class => RedisChecker::class,
                default => null,
            };

            if ($checkerClass === null) {
                continue;
            }

            yield sprintf('healthcheck.checker.%s', $id) => new Definition($checkerClass)
                    ->addArgument(new Reference($id))
            ;
        }
    }
}
