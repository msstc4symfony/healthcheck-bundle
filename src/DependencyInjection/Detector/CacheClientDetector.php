<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector;

use Memcache;
use Memcached;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\MemcacheChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\MemcachedChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\PredisChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\RedisChecker;
use Override;
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
    #[Override]
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
