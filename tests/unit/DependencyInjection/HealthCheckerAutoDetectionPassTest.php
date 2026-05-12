<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Test\Unit\DependencyInjection;

use Doctrine\DBAL\Connection;
use Doctrine\ODM\MongoDB\DocumentManager;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\CacheChecker;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\DBALConnectionChecker;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\ElasticaConnectionChecker;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\MemcacheChecker;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\MemcachedChecker;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\MongoConnectionChecker;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\ODMConnectionChecker;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\PredisChecker;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\RabbitmqChecker;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\RedisChecker;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\CacheClientDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\CachePoolDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\DBALConnectionDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\ElasticaClientDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\MongoConnectionDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\ODMDocumentManagerDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\RabbitMQConnectionDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\HealthCheckerAutoDetectionPass;
use PhpAmqpLib\Connection\AbstractConnection;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use stdClass;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class HealthCheckerAutoDetectionPassTest extends TestCase
{
    public function testProcessRegistersDbalCheckers(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('doctrine.dbal.foo_connection', new Definition(Connection::class));
        $container->setDefinition('doctrine.dbal.bar_connection', new Definition(Connection::class));
        $container->setDefinition('unrelated.service', new Definition(stdClass::class));

        $this->runPass($container);

        $foo = $container->findDefinition('healthcheck.checker.doctrine.dbal.foo_connection');
        self::assertSame(DBALConnectionChecker::class, $foo->getClass());
        self::assertEquals(new Reference('doctrine.dbal.foo_connection'), $foo->getArgument(0));
        self::assertSame('foo', $foo->getArgument(1));
        self::assertTrue($foo->isAutowired());
        self::assertArrayHasKey(CheckInterface::class, $foo->getTags());

        self::assertTrue($container->hasDefinition('healthcheck.checker.doctrine.dbal.bar_connection'));
        self::assertFalse($container->hasDefinition('healthcheck.checker.unrelated.service'));
    }

    public function testProcessRegistersRabbitMqCheckers(): void
    {
        $container = new ContainerBuilder();
        $rabbit = new Definition(AbstractConnection::class);
        $rabbit->addTag('old_sound_rabbit_mq.connection');

        $container->setDefinition('rabbit.conn', $rabbit);

        $this->runPass($container);

        $checker = $container->findDefinition('healthcheck.checker.rabbit.conn');
        self::assertSame(RabbitmqChecker::class, $checker->getClass());
        self::assertEquals(new Reference('rabbit.conn'), $checker->getArgument(0));
        self::assertTrue($checker->isAutowired());
        self::assertArrayHasKey(CheckInterface::class, $checker->getTags());
    }

    public function testProcessRegistersCacheClientCheckers(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.redis', new Definition('Redis'));
        $container->setDefinition('app.memcached', new Definition('Memcached'));
        $container->setDefinition('app.memcache', new Definition('Memcache'));
        $container->setDefinition('app.predis', new Definition(Client::class));
        $container->setDefinition('app.classless', new Definition());
        $container->setDefinition('app.other', new Definition(stdClass::class));

        $this->runPass($container);

        self::assertSame(RedisChecker::class, $container->findDefinition('healthcheck.checker.app.redis')->getClass());
        self::assertSame(MemcachedChecker::class, $container->findDefinition('healthcheck.checker.app.memcached')->getClass());
        self::assertSame(MemcacheChecker::class, $container->findDefinition('healthcheck.checker.app.memcache')->getClass());
        self::assertSame(PredisChecker::class, $container->findDefinition('healthcheck.checker.app.predis')->getClass());
        self::assertFalse($container->hasDefinition('healthcheck.checker.app.classless'));
        self::assertFalse($container->hasDefinition('healthcheck.checker.app.other'));
    }

    public function testProcessRegistersMongoCheckers(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('doctrine_mongodb.odm.default_connection', new Definition(\MongoDB\Client::class));
        $container->setDefinition('doctrine_mongodb.something_else', new Definition(\MongoDB\Client::class));

        $this->runPass($container);

        $checker = $container->findDefinition('healthcheck.checker.doctrine_mongodb.odm.default_connection');
        self::assertSame(MongoConnectionChecker::class, $checker->getClass());
        self::assertSame('default', $checker->getArgument(1));
        self::assertTrue($checker->isAutowired());
        self::assertArrayHasKey(CheckInterface::class, $checker->getTags());

        self::assertFalse($container->hasDefinition('healthcheck.checker.doctrine_mongodb.something_else'));
    }

    public function testProcessRegistersOdmDocumentManagerCheckers(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(
            'doctrine_mongodb.odm.default_document_manager',
            new Definition(DocumentManager::class),
        );
        $container->setDefinition(
            'doctrine_mongodb.odm.other_thing',
            new Definition(DocumentManager::class),
        );

        $this->runPass($container);

        $checker = $container->findDefinition('healthcheck.checker.doctrine_mongodb.odm.default_document_manager');
        self::assertSame(ODMConnectionChecker::class, $checker->getClass());
        self::assertSame('default', $checker->getArgument(1));
        self::assertTrue($checker->isAutowired());
        self::assertArrayHasKey(CheckInterface::class, $checker->getTags());

        self::assertFalse($container->hasDefinition('healthcheck.checker.doctrine_mongodb.odm.other_thing'));
    }

    public function testProcessRegistersElasticaCheckers(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('elastica.client.main', new Definition(\Elastica\Client::class));

        $this->runPass($container);

        $checker = $container->findDefinition('healthcheck.checker.elastica.client.main');
        self::assertSame(ElasticaConnectionChecker::class, $checker->getClass());
        self::assertSame('elastica.client.main', $checker->getArgument(1));
        self::assertTrue($checker->isAutowired());
    }

    public function testProcessRegistersCachePoolChecker(): void
    {
        $container = new ContainerBuilder();
        $pool = new Definition(FilesystemAdapter::class);
        $pool->addTag('cache.pool', ['name' => 'my_pool']);

        $container->setDefinition('cache.app', $pool);

        $this->runPass($container);

        $checker = $container->findDefinition('healthcheck.checker.cache.pool.my_pool');
        self::assertSame(CacheChecker::class, $checker->getClass());
        self::assertEquals(new Reference('cache.app'), $checker->getArgument(0));
        self::assertSame('my_pool', $checker->getArgument(1));
        self::assertNull($checker->getArgument(2));
    }

    public function testProcessSkipsAbstractCachePool(): void
    {
        $container = new ContainerBuilder();
        $pool = new Definition(FilesystemAdapter::class);
        $pool->setAbstract(true);
        $pool->addTag('cache.pool', ['name' => 'abstract_pool']);

        $container->setDefinition('cache.abstract', $pool);

        $this->runPass($container);

        self::assertFalse($container->hasDefinition('healthcheck.checker.cache.pool.abstract_pool'));
    }

    public function testProcessInheritsCachePoolNameFromParent(): void
    {
        $container = new ContainerBuilder();

        $parent = new Definition(RedisAdapter::class);
        $parent->setAbstract(true);
        $parent->addTag('cache.pool', ['name' => 'inherited_name']);

        $container->setDefinition('cache.adapter.redis', $parent);

        // Child tag has no "name" — must be filled in from parent's tag (this exercises the bugfix).
        $child = new ChildDefinition('cache.adapter.redis');
        $child->addTag('cache.pool', []);

        $container->setDefinition('cache.child_pool', $child);

        $this->runPass($container);

        $checker = $container->findDefinition('healthcheck.checker.cache.pool.inherited_name');
        self::assertSame('inherited_name', $checker->getArgument(1));
        self::assertSame(RedisAdapter::class, $checker->getArgument(2));
    }

    public function testProcessPreservesChildCachePoolNameOverParent(): void
    {
        $container = new ContainerBuilder();

        $parent = new Definition(RedisAdapter::class);
        $parent->setAbstract(true);
        $parent->addTag('cache.pool', ['name' => 'parent_name']);

        $container->setDefinition('cache.adapter.redis', $parent);

        // Child has its own "name" — it must win over the parent's during the tag merge.
        // The original buggy code reassigned $tags to the parent's tags inside the loop, losing the child's name.
        $child = new ChildDefinition('cache.adapter.redis');
        $child->addTag('cache.pool', ['name' => 'child_name']);

        $container->setDefinition('cache.child_pool', $child);

        $this->runPass($container);

        self::assertTrue($container->hasDefinition('healthcheck.checker.cache.pool.child_name'));
        self::assertFalse($container->hasDefinition('healthcheck.checker.cache.pool.parent_name'));
    }

    public function testProcessRegistersBothDbalAndMongoInSingleRun(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('doctrine.dbal.default_connection', new Definition(Connection::class));
        $container->setDefinition('doctrine_mongodb.odm.default_connection', new Definition(\MongoDB\Client::class));

        $this->runPass($container);

        self::assertTrue($container->hasDefinition('healthcheck.checker.doctrine.dbal.default_connection'));
        self::assertTrue($container->hasDefinition('healthcheck.checker.doctrine_mongodb.odm.default_connection'));
    }

    private function runPass(ContainerBuilder $container): void
    {
        new HealthCheckerAutoDetectionPass([
            new DBALConnectionDetector(),
            new RabbitMQConnectionDetector(),
            new CacheClientDetector(),
            new CachePoolDetector(),
            new MongoConnectionDetector(),
            new ODMDocumentManagerDetector(),
            new ElasticaClientDetector(),
        ])->process($container);
    }
}
