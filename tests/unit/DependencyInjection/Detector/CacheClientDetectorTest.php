<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\DependencyInjection\Detector;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\MemcacheChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\MemcachedChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\PredisChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\RedisChecker;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\CacheClientDetector;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use stdClass;
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

        $detected = iterator_to_array(new CacheClientDetector()->detect($container));

        self::assertSame(RedisChecker::class, $detected['healthcheck.checker.app.redis']->getClass());
        self::assertSame(MemcachedChecker::class, $detected['healthcheck.checker.app.memcached']->getClass());
        self::assertSame(MemcacheChecker::class, $detected['healthcheck.checker.app.memcache']->getClass());
        self::assertSame(PredisChecker::class, $detected['healthcheck.checker.app.predis']->getClass());

        $redis = $detected['healthcheck.checker.app.redis'];
        self::assertEquals(new Reference('app.redis'), $redis->getArgument(0));
    }

    public function testDetectSkipsDefinitionsWithoutClass(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.classless', new Definition());

        self::assertSame([], iterator_to_array(new CacheClientDetector()->detect($container)));
    }

    public function testDetectSkipsUnrelatedClasses(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.other', new Definition(stdClass::class));

        self::assertSame([], iterator_to_array(new CacheClientDetector()->detect($container)));
    }
}
