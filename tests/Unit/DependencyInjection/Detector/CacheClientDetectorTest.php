<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\DependencyInjection\Detector;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\MemcacheChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\MemcachedChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\PredisChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\RedisChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\RedisClusterChecker;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\CacheClientDetector;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Predis\SubclassedClient;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Redis\SubclassedRedis;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Redis\SubclassedRedisCluster;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use stdClass;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class CacheClientDetectorTest extends TestCase
{
    public function testDetectMapsEachSupportedClientToItsChecker(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.redis', new Definition('Redis'));
        $container->setDefinition('app.memcached', new Definition('Memcached'));
        $container->setDefinition('app.memcache', new Definition('Memcache'));
        $container->setDefinition('app.predis', new Definition(Client::class));
        $container->setDefinition('app.redis_cluster', new Definition('RedisCluster'));

        $detected = iterator_to_array(new CacheClientDetector()->detect($container));

        self::assertSame(RedisChecker::class, $detected['healthcheck.checker.app.redis']->getClass());
        self::assertSame(MemcachedChecker::class, $detected['healthcheck.checker.app.memcached']->getClass());
        self::assertSame(MemcacheChecker::class, $detected['healthcheck.checker.app.memcache']->getClass());
        self::assertSame(PredisChecker::class, $detected['healthcheck.checker.app.predis']->getClass());
        self::assertSame(RedisClusterChecker::class, $detected['healthcheck.checker.app.redis_cluster']->getClass());
        self::assertCount(5, $detected);

        $redis = $detected['healthcheck.checker.app.redis'];
        self::assertEquals(new Reference('app.redis'), $redis->getArgument(0));
    }

    #[RequiresPhpExtension('redis')]
    public function testDetectMapsRedisSubclassesToTheirCheckers(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('snc_redis.default', new Definition(SubclassedRedis::class));
        $container->setDefinition('snc_redis.cluster', new Definition(SubclassedRedisCluster::class));
        $container->setParameter('app.redis_class', SubclassedRedis::class);
        $container->setDefinition('app.parametrised', new Definition('%app.redis_class%'));
        $container->setDefinition('app.redis_prototype', new Definition(SubclassedRedis::class)->setAbstract(true));
        $container->setDefinition('app.redis_child', new ChildDefinition('app.redis_prototype'));

        $detected = iterator_to_array(new CacheClientDetector()->detect($container));

        self::assertSame(
            [
                'healthcheck.checker.snc_redis.default' => RedisChecker::class,
                'healthcheck.checker.snc_redis.cluster' => RedisClusterChecker::class,
                'healthcheck.checker.app.parametrised' => RedisChecker::class,
                'healthcheck.checker.app.redis_child' => RedisChecker::class,
            ],
            array_map(static fn (Definition $checker): ?string => $checker->getClass(), $detected),
        );
        self::assertEquals(new Reference('snc_redis.cluster'), $detected['healthcheck.checker.snc_redis.cluster']->getArgument(0));
    }

    #[RequiresMethod(Client::class, '__call')]
    public function testDetectMapsPredisSubclassToPredisChecker(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.predis', new Definition(SubclassedClient::class));

        $detected = iterator_to_array(new CacheClientDetector()->detect($container));

        self::assertSame(['healthcheck.checker.app.predis'], array_keys($detected));
        self::assertSame(PredisChecker::class, $detected['healthcheck.checker.app.predis']->getClass());
    }

    public function testDetectSkipsDefinitionsWithoutClass(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.classless', new Definition());

        self::assertSame([], iterator_to_array(new CacheClientDetector()->detect($container)));
    }

    public function testDetectSkipsAbstractDefinitions(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.redis_prototype', new Definition('Redis')->setAbstract(true));

        self::assertSame([], iterator_to_array(new CacheClientDetector()->detect($container)));
    }

    public function testDetectSkipsUnrelatedClasses(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.other', new Definition(stdClass::class));

        self::assertSame([], iterator_to_array(new CacheClientDetector()->detect($container)));
    }
}
