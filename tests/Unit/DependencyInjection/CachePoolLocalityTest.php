<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\DependencyInjection;

use Msstc4Symfony\HealthCheckBundle\DependencyInjection\CachePoolLocality;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class CachePoolLocalityTest extends TestCase
{
    public function testSystemCacheFactoryIsLocal(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('cache.adapter.system', new Definition(AdapterInterface::class)->setFactory([AbstractAdapter::class, 'createSystemCache']));

        self::assertTrue(CachePoolLocality::isLocal($container, new ChildDefinition('cache.adapter.system')));
    }

    public function testChildFactoryOverridesParentFactory(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('cache.adapter.system', new Definition(AdapterInterface::class)->setFactory([AbstractAdapter::class, 'createSystemCache']));

        self::assertFalse(CachePoolLocality::isLocal($container, new ChildDefinition('cache.adapter.system')->setFactory([RedisAdapter::class, 'createConnection'])));
    }

    public function testChildClassOverridesParentClass(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('cache.adapter.array', new Definition(ArrayAdapter::class));

        self::assertFalse(CachePoolLocality::isLocal($container, new ChildDefinition('cache.adapter.array')->setClass(RedisAdapter::class)));
    }

    public function testChainWithoutAdaptersIsNotLocal(): void
    {
        self::assertFalse(CachePoolLocality::isLocal(new ContainerBuilder(), new Definition(ChainAdapter::class, [[], 0])));
    }

    public function testDefinitionWithoutClassIsNotLocal(): void
    {
        self::assertFalse(CachePoolLocality::isLocal(new ContainerBuilder(), new Definition()));
    }
}
