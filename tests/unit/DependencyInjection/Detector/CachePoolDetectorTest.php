<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Test\Unit\DependencyInjection\Detector;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\CacheChecker;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\CachePoolDetector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class CachePoolDetectorTest extends TestCase
{
    public function testDetectYieldsCheckerForTaggedPool(): void
    {
        $container = new ContainerBuilder();
        $pool = new Definition(FilesystemAdapter::class);
        $pool->addTag('cache.pool', ['name' => 'my_pool']);

        $container->setDefinition('cache.app', $pool);

        $detected = iterator_to_array(new CachePoolDetector()->detect($container));

        $checker = $detected['healthcheck.checker.cache.pool.my_pool'];
        self::assertSame(CacheChecker::class, $checker->getClass());
        self::assertEquals(new Reference('cache.app'), $checker->getArgument(0));
        self::assertSame('my_pool', $checker->getArgument(1));
        self::assertNull($checker->getArgument(2));
    }

    public function testDetectSkipsAbstractPool(): void
    {
        $container = new ContainerBuilder();
        $pool = new Definition(FilesystemAdapter::class);
        $pool->setAbstract(true);
        $pool->addTag('cache.pool', ['name' => 'abstract_pool']);

        $container->setDefinition('cache.abstract', $pool);

        self::assertSame([], iterator_to_array(new CachePoolDetector()->detect($container)));
    }

    public function testDetectInheritsNameAndClassFromParent(): void
    {
        $container = new ContainerBuilder();

        $parent = new Definition(RedisAdapter::class);
        $parent->setAbstract(true);
        $parent->addTag('cache.pool', ['name' => 'inherited_name']);

        $container->setDefinition('cache.adapter.redis', $parent);

        $child = new ChildDefinition('cache.adapter.redis');
        $child->addTag('cache.pool', []);

        $container->setDefinition('cache.child_pool', $child);

        $detected = iterator_to_array(new CachePoolDetector()->detect($container));

        $checker = $detected['healthcheck.checker.cache.pool.inherited_name'];
        self::assertSame('inherited_name', $checker->getArgument(1));
        self::assertSame(RedisAdapter::class, $checker->getArgument(2));
    }

    public function testDetectChildNameWinsOverParentName(): void
    {
        $container = new ContainerBuilder();

        $parent = new Definition(RedisAdapter::class);
        $parent->setAbstract(true);
        $parent->addTag('cache.pool', ['name' => 'parent_name']);

        $container->setDefinition('cache.adapter.redis', $parent);

        $child = new ChildDefinition('cache.adapter.redis');
        $child->addTag('cache.pool', ['name' => 'child_name']);

        $container->setDefinition('cache.child_pool', $child);

        $detected = iterator_to_array(new CachePoolDetector()->detect($container));

        self::assertArrayHasKey('healthcheck.checker.cache.pool.child_name', $detected);
        self::assertArrayNotHasKey('healthcheck.checker.cache.pool.parent_name', $detected);
    }

    public function testDetectFallsBackToServiceIdWhenTagHasNoName(): void
    {
        $container = new ContainerBuilder();
        $pool = new Definition(FilesystemAdapter::class);
        $pool->addTag('cache.pool', []);

        $container->setDefinition('cache.app', $pool);

        $detected = iterator_to_array(new CachePoolDetector()->detect($container));

        self::assertArrayHasKey('healthcheck.checker.cache.pool.cache.app', $detected);
    }

    public function testDetectReturnsEmptyWhenNoCachePoolTagsPresent(): void
    {
        self::assertSame([], iterator_to_array(new CachePoolDetector()->detect(new ContainerBuilder())));
    }
}
