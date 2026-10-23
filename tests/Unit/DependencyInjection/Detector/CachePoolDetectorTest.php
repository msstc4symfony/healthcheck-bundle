<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\DependencyInjection\Detector;

use Msstc4Symfony\HealthCheckBundle\DependencyInjection\CachePoolConnection;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\ContainerIds;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\CachePoolDetector;
use Msstc4Symfony\HealthCheckBundle\Infrastructure\Checker\CacheChecker;
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
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
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

        $detected = iterator_to_array((new CachePoolDetector())->detect($container));

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

        self::assertSame([], iterator_to_array((new CachePoolDetector())->detect($container)));
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

        $detected = iterator_to_array((new CachePoolDetector())->detect($container));

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

        $detected = iterator_to_array((new CachePoolDetector())->detect($container));

        self::assertArrayHasKey('healthcheck.checker.cache.pool.child_name', $detected);
        self::assertArrayNotHasKey('healthcheck.checker.cache.pool.parent_name', $detected);
    }

    public function testDetectFallsBackToServiceIdWhenTagHasNoName(): void
    {
        $container = new ContainerBuilder();
        $pool = new Definition(RedisAdapter::class);
        $pool->addTag('cache.pool', []);

        $container->setDefinition('cache.app', $pool);

        $detected = iterator_to_array((new CachePoolDetector())->detect($container));

        self::assertArrayHasKey('healthcheck.checker.cache.pool.cache.app', $detected);
    }

    /**
     * @param class-string $adapterClass
     */
    #[DataProvider('provideRemoteAdapters')]
    public function testDetectProbesPoolsBackedByRemoteAdapters(string $adapterClass): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('cache.adapter.remote', (new Definition($adapterClass))->setAbstract(true)->addTag('cache.pool'));
        $container->setDefinition('app.remote_pool', (new ChildDefinition('cache.adapter.remote'))->addTag('cache.pool'));

        self::assertSame(
            ['healthcheck.checker.cache.pool.app.remote_pool'],
            array_keys(iterator_to_array((new CachePoolDetector())->detect($container))),
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
        $container->setDefinition('cache.adapter.local', (new Definition($adapterClass))->setAbstract(true)->addTag('cache.pool'));
        $container->setDefinition('app.local_pool', (new ChildDefinition('cache.adapter.local'))->addTag('cache.pool'));

        self::assertSame([], iterator_to_array((new CachePoolDetector())->detect($container)));
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
        $container->setDefinition('cache.adapter.system', (new Definition(AdapterInterface::class))
            ->setAbstract(true)
            ->setFactory([AbstractAdapter::class, 'createSystemCache'])
            ->addTag('cache.pool'));
        $container->setDefinition('cache.system', (new ChildDefinition('cache.adapter.system'))->addTag('cache.pool'));
        $container->setDefinition('cache.validator', (new ChildDefinition('cache.system'))->addTag('cache.pool'));
        $container->setDefinition('cache.validator_expression_language', (new ChildDefinition('cache.system'))->addTag('cache.pool'));

        self::assertSame([], iterator_to_array((new CachePoolDetector())->detect($container)));
    }

    public function testDetectProbesChildWhoseOwnFactoryOverridesTheSystemCacheFactory(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('cache.adapter.system', (new Definition(AdapterInterface::class))
            ->setAbstract(true)
            ->setFactory([AbstractAdapter::class, 'createSystemCache'])
            ->addTag('cache.pool'));
        $container->setDefinition('app.remote_pool', (new ChildDefinition('cache.adapter.system'))
            ->setFactory([RedisAdapter::class, 'createConnection'])
            ->addTag('cache.pool'));

        self::assertArrayHasKey('healthcheck.checker.cache.pool.app.remote_pool', iterator_to_array((new CachePoolDetector())->detect($container)));
    }

    /**
     * @param list<string|Reference|ChildDefinition> $chained
     */
    #[DataProvider('provideChains')]
    public function testDetectProbesChainOnlyWhenAMemberIsRemote(array $chained, bool $probed): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('cache.adapter.array', (new Definition(ArrayAdapter::class))->setAbstract(true)->addTag('cache.pool'));
        $container->setDefinition('cache.adapter.filesystem', (new Definition(FilesystemAdapter::class))->setAbstract(true)->addTag('cache.pool'));
        $container->setDefinition('cache.adapter.redis', (new Definition(RedisAdapter::class))->setAbstract(true)->addTag('cache.pool'));
        $container->setDefinition('app.chain', (new Definition(ChainAdapter::class, [$chained, 0]))->addTag('cache.pool'));

        self::assertSame($probed, iterator_to_array((new CachePoolDetector())->detect($container)) !== []);
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

    public function testEveryPoolKeepsItsOwnCheckerAndPoolsOnOneConnectionShareAKey(): void
    {
        $container = $this->poolsOn([
            'cache.app' => '.cache_connection.aaa',
            'metrics.cache' => '.cache_connection.aaa',
            'other' => '.cache_connection.bbb',
        ]);

        $detected = iterator_to_array((new CachePoolDetector())->detect($container));

        self::assertSame(
            ['healthcheck.checker.cache.pool.cache.app', 'healthcheck.checker.cache.pool.metrics.cache', 'healthcheck.checker.cache.pool.other'],
            array_keys($detected),
        );
        self::assertSame('redis:.cache_connection.aaa', $detected['healthcheck.checker.cache.pool.cache.app']->getArgument(3));
        self::assertSame('redis:.cache_connection.aaa', $detected['healthcheck.checker.cache.pool.metrics.cache']->getArgument(3));
        self::assertSame('redis:.cache_connection.bbb', $detected['healthcheck.checker.cache.pool.other']->getArgument(3));
    }

    public function testShareKeyResolvesAliasChainsOfTheProvider(): void
    {
        $container = $this->poolsOn(['a' => 'cache.default_redis_provider', 'b' => '.cache_connection.aaa', 'c' => 'app.alias']);
        $container->setAlias('cache.default_redis_provider', '.cache_connection.aaa');
        $container->setAlias('app.alias', 'cache.default_redis_provider');

        self::assertSame(['redis:.cache_connection.aaa'], $this->shareKeys($container));
    }

    public function testShareKeyIsNullForACyclicAliasChain(): void
    {
        $container = $this->poolsOn(['a' => 'x']);
        $container->setAlias('x', 'y');
        $container->setAlias('y', 'x');

        self::assertSame([null], array_values($this->keysByPool($container)));
    }

    /**
     * @param class-string $adapterClass
     */
    #[DataProvider('provideSharedKinds')]
    public function testSharesOnlyAdaptersWhoseWholeBackendIsTheConnection(string $adapterClass, string $kind): void
    {
        $container = $this->poolsOn(['a' => 'app.connection', 'b' => 'app.connection'], $adapterClass);

        self::assertSame([$kind . ':app.connection'], $this->shareKeys($container));
    }

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function provideSharedKinds(): iterable
    {
        yield 'redis' => [RedisAdapter::class, 'redis'];
        yield 'redis tag aware' => [RedisTagAwareAdapter::class, 'redis_tag_aware'];
        yield 'memcached' => [MemcachedAdapter::class, 'memcached'];
        yield 'couchbase' => [CouchbaseCollectionAdapter::class, 'couchbase'];
    }

    /**
     * @param class-string $adapterClass
     */
    #[DataProvider('provideUnsharedAdapters')]
    public function testNeverSharesTablePoolsOrThirdPartyAdapters(string $adapterClass): void
    {
        $container = $this->poolsOn(['a' => 'app.connection', 'b' => 'app.connection'], $adapterClass);

        self::assertSame(['a' => null, 'b' => null], $this->keysByPool($container));
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function provideUnsharedAdapters(): iterable
    {
        yield 'pdo' => [PdoAdapter::class];
        yield 'doctrine dbal' => [DoctrineDbalAdapter::class];
        yield 'third-party adapter' => [stdClass::class];
    }

    public function testATagAwarePoolAndAPlainPoolOnOneConnectionDoNotShareAKey(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('plain', (new Definition(RedisAdapter::class, [new Reference('c')]))->addTag('cache.pool', ['name' => 'plain']));
        $container->setDefinition('tagged', (new Definition(RedisTagAwareAdapter::class, [new Reference('c')]))->addTag('cache.pool', ['name' => 'tagged']));

        self::assertSame(['plain' => 'redis:c', 'tagged' => 'redis_tag_aware:c'], $this->keysByPool($container));
    }

    public function testPoolsWithoutAPositionalConnectionReferenceAreNotShared(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('dsn', (new Definition(RedisAdapter::class, ['redis://x']))->addTag('cache.pool', ['name' => 'dsn']));
        $container->setDefinition('none', (new Definition(RedisAdapter::class))->addTag('cache.pool', ['name' => 'none']));
        $container->setDefinition('named', (new Definition(RedisAdapter::class, ['$redis' => new Reference('c')]))->addTag('cache.pool', ['name' => 'named']));

        self::assertSame(['dsn' => null, 'none' => null, 'named' => null], $this->keysByPool($container));
    }

    public function testATagsPoolIsNotSharedBecauseItsFirstArgumentIsTheInnerPool(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('.app.inner', new Definition(RedisAdapter::class, [new Reference('c')]));
        $container->setDefinition('app', (new Definition(TagAwareAdapter::class, [new Reference('.app.inner')]))->addTag('cache.pool', ['name' => 'app']));
        $container->setDefinition('plain', (new Definition(RedisAdapter::class, [new Reference('c')]))->addTag('cache.pool', ['name' => 'plain']));

        self::assertSame(['app' => null, 'plain' => 'redis:c'], $this->keysByPool($container));
    }

    public function testShareKeyIsResolvedFromTheParentChain(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('cache.adapter.redis', (new Definition(RedisAdapter::class, [new Reference('app.parent_connection')]))->setAbstract(true));
        $container->setDefinition('a', (new ChildDefinition('cache.adapter.redis'))->addTag('cache.pool', ['name' => 'a']));
        $container->setDefinition('b', (new ChildDefinition('cache.adapter.redis'))->replaceArgument(0, new Reference('app.other'))->addTag('cache.pool', ['name' => 'b']));

        self::assertSame(['a' => 'redis:app.parent_connection', 'b' => 'redis:app.other'], $this->keysByPool($container));
    }

    public function testShareKeyPrefersTheChildClassOverTheParentClass(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('cache.adapter.generic', (new Definition(stdClass::class))->setAbstract(true));
        $container->setDefinition('a', (new ChildDefinition('cache.adapter.generic'))
            ->setClass(RedisAdapter::class)
            ->replaceArgument(0, new Reference('c'))
            ->addTag('cache.pool', ['name' => 'a']));

        self::assertSame(['a' => 'redis:c'], $this->keysByPool($container));
    }

    public function testShareKeyIsNullForACyclicOrBrokenParentChain(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('x', (new ChildDefinition('y'))->setClass(RedisAdapter::class));
        $container->setDefinition('y', (new ChildDefinition('x'))->setClass(RedisAdapter::class));
        $container->setDefinition('z', (new ChildDefinition('missing'))->setClass(RedisAdapter::class));

        self::assertNull(CachePoolConnection::shareKey($container, $container->getDefinition('x')));
        self::assertNull(CachePoolConnection::shareKey($container, $container->getDefinition('z')));
    }

    public function testARootDefinitionsIndexedArgumentWinsOverItsNumericOne(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('a', (new Definition(RedisAdapter::class))
            ->setArgument(0, new Reference('app.numeric'))
            ->setArgument('index_0', new Reference('app.indexed'))
            ->addTag('cache.pool', ['name' => 'a']));

        self::assertSame(['a' => 'redis:app.indexed'], $this->keysByPool($container));
    }

    public function testAChildsAppendedNumericArgumentIsNotPositionZero(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('cache.adapter.redis', (new Definition(RedisAdapter::class, [new Reference('app.parent_connection')]))->setAbstract(true));
        $container->setDefinition('a', (new ChildDefinition('cache.adapter.redis'))->addArgument(new Reference('app.appended'))->addTag('cache.pool', ['name' => 'a']));

        self::assertSame(['a' => 'redis:app.parent_connection'], $this->keysByPool($container));
    }

    public function testASharedPoolCarriesTheKeyAsATagForTheDeferredDecorator(): void
    {
        $container = $this->poolsOn(['a' => 'c']);
        $container->setDefinition('plain', (new Definition(RedisAdapter::class))->addTag('cache.pool', ['name' => 'plain']));

        $detected = iterator_to_array((new CachePoolDetector())->detect($container));

        self::assertSame([['key' => 'redis:c']], $detected['healthcheck.checker.cache.pool.a']->getTag(ContainerIds::TAG_SHARED_PROBE));
        self::assertFalse($detected['healthcheck.checker.cache.pool.plain']->hasTag(ContainerIds::TAG_SHARED_PROBE));
    }

    public function testChainsAreNotShared(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('cache.adapter.redis', (new Definition(RedisAdapter::class))->setAbstract(true));

        $redis = (new ChildDefinition('cache.adapter.redis'))->replaceArgument(0, new Reference('c'));
        $container->setDefinition('a', (new Definition(ChainAdapter::class, [[$redis], 0]))->addTag('cache.pool', ['name' => 'a']));

        self::assertSame(['a' => null], $this->keysByPool($container));
    }

    public function testLocalPoolsAreNotYieldedEvenOnAConnection(): void
    {
        $container = $this->poolsOn(['a' => 'c']);
        $container->setDefinition('local', (new Definition(FilesystemAdapter::class, [new Reference('c')]))->addTag('cache.pool', ['name' => 'local']));

        self::assertSame(['a' => 'redis:c'], $this->keysByPool($container));
    }

    /**
     * @return list<string|null> distinct share keys in the order they are first seen
     */
    private function shareKeys(ContainerBuilder $container): array
    {
        return array_values(array_unique($this->keysByPool($container)));
    }

    /**
     * @return array<string, string|null> pool name => share key
     */
    private function keysByPool(ContainerBuilder $container): array
    {
        $keys = [];
        foreach ((new CachePoolDetector())->detect($container) as $id => $checker) {
            $name = substr($id, strlen('healthcheck.checker.cache.pool.'));
            $key = $checker->getArgument(3);
            self::assertTrue($key === null || is_string($key));
            $keys[$name] = $key;
        }
        self::assertNotSame([], $keys);

        return $keys;
    }

    /**
     * @param array<string, string> $connectionByPool pool name => the connection service id its first argument references
     * @param class-string $adapterClass
     */
    private function poolsOn(array $connectionByPool, string $adapterClass = RedisAdapter::class): ContainerBuilder
    {
        $container = new ContainerBuilder();
        foreach ($connectionByPool as $name => $connection) {
            $container->setDefinition($name, (new Definition($adapterClass, [new Reference($connection)]))->addTag('cache.pool', ['name' => $name]));
        }

        return $container;
    }
}
