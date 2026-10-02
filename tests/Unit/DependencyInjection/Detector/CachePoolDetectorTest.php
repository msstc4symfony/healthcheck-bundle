<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\DependencyInjection\Detector;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CacheChecker;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\CachePoolDetector;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Cache\FilesystemAdapterSubclassFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\CouchbaseCollectionAdapter;
use Symfony\Component\Cache\Adapter\DoctrineDbalAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class CachePoolDetectorTest extends TestCase
{
    public function testDetectYieldsCheckerForTaggedPool(): void
    {
        $container = new ContainerBuilder();
        $pool = new Definition(RedisAdapter::class);
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
        $pool = new Definition(RedisAdapter::class);
        $pool->addTag('cache.pool', []);

        $container->setDefinition('cache.app', $pool);

        $detected = iterator_to_array(new CachePoolDetector()->detect($container));

        self::assertArrayHasKey('healthcheck.checker.cache.pool.cache.app', $detected);
    }

    /**
     * @param class-string $adapterClass
     */
    #[DataProvider('provideRemoteAdapters')]
    public function testDetectProbesPoolsBackedByRemoteAdapters(string $adapterClass): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('cache.adapter.remote', new Definition($adapterClass)->setAbstract(true)->addTag('cache.pool'));
        $container->setDefinition('app.remote_pool', new ChildDefinition('cache.adapter.remote')->addTag('cache.pool'));

        self::assertSame(
            ['healthcheck.checker.cache.pool.app.remote_pool'],
            array_keys(iterator_to_array(new CachePoolDetector()->detect($container))),
        );
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function provideRemoteAdapters(): iterable
    {
        yield 'redis' => [RedisAdapter::class];
        yield 'redis tag aware' => [RedisTagAwareAdapter::class];
        yield 'memcached' => [MemcachedAdapter::class];
        yield 'pdo' => [PdoAdapter::class];
        yield 'doctrine dbal' => [DoctrineDbalAdapter::class];
        yield 'couchbase' => [CouchbaseCollectionAdapter::class];
        yield 'third-party adapter' => [stdClass::class];
    }

    /**
     * @param class-string $adapterClass
     */
    #[DataProvider('provideLocalAdapters')]
    public function testDetectSkipsPoolsBackedByLocalAdapters(string $adapterClass): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('cache.adapter.local', new Definition($adapterClass)->setAbstract(true)->addTag('cache.pool'));
        $container->setDefinition('app.local_pool', new ChildDefinition('cache.adapter.local')->addTag('cache.pool'));

        self::assertSame([], iterator_to_array(new CachePoolDetector()->detect($container)));
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function provideLocalAdapters(): iterable
    {
        yield 'array' => [ArrayAdapter::class];
        yield 'apcu' => [ApcuAdapter::class];
        yield 'filesystem' => [FilesystemAdapter::class];
        yield 'filesystem tag aware' => [FilesystemTagAwareAdapter::class];
        yield 'php files' => [PhpFilesAdapter::class];
        yield 'php array' => [PhpArrayAdapter::class];
        yield 'null' => [NullAdapter::class];
        yield 'subclass of a local adapter' => [FilesystemAdapterSubclassFixture::class];
    }

    public function testDetectSkipsSystemCacheAndPoolsInheritingFromIt(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('cache.adapter.system', new Definition(AdapterInterface::class)
            ->setAbstract(true)
            ->setFactory([AbstractAdapter::class, 'createSystemCache'])
            ->addTag('cache.pool'));
        $container->setDefinition('cache.system', new ChildDefinition('cache.adapter.system')->addTag('cache.pool'));
        $container->setDefinition('cache.validator', new ChildDefinition('cache.system')->addTag('cache.pool'));
        $container->setDefinition('cache.validator_expression_language', new ChildDefinition('cache.system')->addTag('cache.pool'));

        self::assertSame([], iterator_to_array(new CachePoolDetector()->detect($container)));
    }

    public function testDetectProbesChildWhoseOwnFactoryOverridesTheSystemCacheFactory(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('cache.adapter.system', new Definition(AdapterInterface::class)
            ->setAbstract(true)
            ->setFactory([AbstractAdapter::class, 'createSystemCache'])
            ->addTag('cache.pool'));
        $container->setDefinition('app.remote_pool', new ChildDefinition('cache.adapter.system')
            ->setFactory([RedisAdapter::class, 'createConnection'])
            ->addTag('cache.pool'));

        self::assertArrayHasKey('healthcheck.checker.cache.pool.app.remote_pool', iterator_to_array(new CachePoolDetector()->detect($container)));
    }

    /**
     * @param list<string|Reference|ChildDefinition> $chained
     */
    #[DataProvider('provideChains')]
    public function testDetectProbesChainOnlyWhenAMemberIsRemote(array $chained, bool $probed): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('cache.adapter.array', new Definition(ArrayAdapter::class)->setAbstract(true)->addTag('cache.pool'));
        $container->setDefinition('cache.adapter.filesystem', new Definition(FilesystemAdapter::class)->setAbstract(true)->addTag('cache.pool'));
        $container->setDefinition('cache.adapter.redis', new Definition(RedisAdapter::class)->setAbstract(true)->addTag('cache.pool'));
        $container->setDefinition('app.chain', new Definition(ChainAdapter::class, [$chained, 0])->addTag('cache.pool'));

        self::assertSame($probed, iterator_to_array(new CachePoolDetector()->detect($container)) !== []);
    }

    /**
     * @return iterable<string, array{list<string|Reference|ChildDefinition>, bool}>
     */
    public static function provideChains(): iterable
    {
        yield 'configured ids, all local' => [['cache.adapter.array', 'cache.adapter.filesystem'], false];
        yield 'configured ids, one remote' => [['cache.adapter.array', 'cache.adapter.redis'], true];
        yield 'references, all local' => [[new Reference('cache.adapter.array'), new Reference('cache.adapter.filesystem')], false];
        yield 'references, one remote' => [[new Reference('cache.adapter.array'), new Reference('cache.adapter.redis')], true];
        yield 'unknown member' => [['cache.adapter.array', 'app.missing_adapter'], true];
        // CachePoolPass (runs before detection) replaces the ids with inline child definitions.
        yield 'compiled children, all local' => [[new ChildDefinition('cache.adapter.array'), new ChildDefinition('cache.adapter.filesystem')], false];
        yield 'compiled children, one remote' => [[new ChildDefinition('cache.adapter.array'), new ChildDefinition('cache.adapter.redis')], true];
    }

    public function testDetectReturnsEmptyWhenNoCachePoolTagsPresent(): void
    {
        self::assertSame([], iterator_to_array(new CachePoolDetector()->detect(new ContainerBuilder())));
    }
}
