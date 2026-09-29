<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\DependencyInjection;

use ClickHouseDB\Client as ClickHouseClient;
use Doctrine\DBAL\Connection;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CacheChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\ClickHouseChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\DBALConnectionChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\DoctrineMigrationsChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\ElasticaConnectionChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\EntityManagerChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\FlysystemChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\HttpClientChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\KafkaChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\LockStoreChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\MailerChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\MemcacheChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\MemcachedChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\MessengerTransportChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\MongoConnectionChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\ODMConnectionChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\OpenSearchChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\PredisChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\RabbitmqChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\RedisChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\HttpProbeTarget;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\CacheClientDetector;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\CachePoolDetector;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\CheckerDetectorInterface;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\ClickHouseDetector;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\DBALConnectionDetector;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\DoctrineMigrationsDetector;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\ElasticaClientDetector;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\EntityManagerDetector;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\FlysystemDetector;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\HttpClientTargetDetector;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\KafkaDetector;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\LockStoreDetector;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\MailerDetector;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\MessengerTransportDetector;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\MongoConnectionDetector;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\ODMDocumentManagerDetector;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\OpenSearchDetector;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\RabbitMQConnectionDetector;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\HealthCheckerAutoDetectionPass;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\HealthCheckExtension;
use OpenSearch\Client as OpenSearchClient;
use PhpAmqpLib\Connection\AbstractConnection;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use RdKafka\Producer as KafkaProducer;
use stdClass;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;

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

    public function testProcessRegistersMessengerTransportCheckers(): void
    {
        $container = new ContainerBuilder();
        $transport = new Definition(TransportInterface::class);
        $transport->addTag('messenger.receiver');

        $container->setDefinition('messenger.transport.async', $transport);

        $this->runPass($container);

        $checker = $container->findDefinition('healthcheck.checker.messenger.transport.async');
        self::assertSame(MessengerTransportChecker::class, $checker->getClass());
        self::assertSame('async', $checker->getArgument(1));
        self::assertArrayHasKey(CheckInterface::class, $checker->getTags());
    }

    public function testProcessRegistersEntityManagerCheckers(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('doctrine.orm.default_entity_manager', new Definition(EntityManagerInterface::class));
        $container->setDefinition('doctrine.orm.something_else', new Definition(EntityManagerInterface::class));

        $this->runPass($container);

        $checker = $container->findDefinition('healthcheck.checker.doctrine.orm.default_entity_manager');
        self::assertSame(EntityManagerChecker::class, $checker->getClass());
        self::assertSame('default', $checker->getArgument(1));
        self::assertArrayHasKey(CheckInterface::class, $checker->getTags());

        self::assertFalse($container->hasDefinition('healthcheck.checker.doctrine.orm.something_else'));
    }

    public function testProcessRegistersDoctrineMigrationsChecker(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(DependencyFactory::class, new Definition(DependencyFactory::class));

        $this->runPass($container);

        $checker = $container->findDefinition('healthcheck.checker.doctrine_migrations');
        self::assertSame(DoctrineMigrationsChecker::class, $checker->getClass());
        self::assertArrayHasKey(CheckInterface::class, $checker->getTags());
    }

    public function testProcessSkipsDoctrineMigrationsWhenMissing(): void
    {
        $container = new ContainerBuilder();

        $this->runPass($container);

        self::assertFalse($container->hasDefinition('healthcheck.checker.doctrine_migrations'));
    }

    public function testProcessRegistersFlysystemCheckers(): void
    {
        $container = new ContainerBuilder();
        $fs = new Definition(Filesystem::class);
        $fs->addTag('flysystem.storage');

        $container->setDefinition('uploads.storage', $fs);

        $this->runPass($container);

        $checker = $container->findDefinition('healthcheck.checker.uploads.storage');
        self::assertSame(FlysystemChecker::class, $checker->getClass());
        self::assertSame('uploads.storage', $checker->getArgument(1));
        self::assertArrayHasKey(CheckInterface::class, $checker->getTags());
    }

    public function testProcessRegistersMailerCheckers(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('mailer.smtp', new Definition(SmtpTransport::class));

        $this->runPass($container);

        $checker = $container->findDefinition('healthcheck.checker.mailer.smtp');
        self::assertSame(MailerChecker::class, $checker->getClass());
        self::assertSame('mailer.smtp', $checker->getArgument(1));
        self::assertArrayHasKey(CheckInterface::class, $checker->getTags());
    }

    public function testProcessRegistersOpenSearchCheckers(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('opensearch.client.main', new Definition(OpenSearchClient::class));

        $this->runPass($container);

        $checker = $container->findDefinition('healthcheck.checker.opensearch.client.main');
        self::assertSame(OpenSearchChecker::class, $checker->getClass());
        self::assertSame('opensearch.client.main', $checker->getArgument(1));
    }

    public function testProcessRegistersKafkaCheckers(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('kafka.producer', new Definition(KafkaProducer::class));

        $this->runPass($container);

        $checker = $container->findDefinition('healthcheck.checker.kafka.producer');
        self::assertSame(KafkaChecker::class, $checker->getClass());
        self::assertSame('kafka.producer', $checker->getArgument(1));
    }

    public function testProcessRegistersClickHouseCheckers(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('clickhouse.analytics', new Definition(ClickHouseClient::class));

        $this->runPass($container);

        $checker = $container->findDefinition('healthcheck.checker.clickhouse.analytics');
        self::assertSame(ClickHouseChecker::class, $checker->getClass());
        self::assertSame('clickhouse.analytics', $checker->getArgument(1));
    }

    public function testProcessRegistersLockStoreCheckers(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('lock.default.store', new Definition(InMemoryStore::class));

        $this->runPass($container);

        $checker = $container->findDefinition('healthcheck.checker.lock.default.store');
        self::assertSame(LockStoreChecker::class, $checker->getClass());
        self::assertSame('lock.default.store', $checker->getArgument(1));
    }

    public function testProcessRegistersHttpClientTargetsFromConfig(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(HealthCheckExtension::PARAM_HTTP_CLIENT_TARGETS, [
            'upstream' => [
                'url' => 'https://api.example.com/health',
                'method' => 'GET',
                'expected_status_codes' => [200],
                'client' => null,
                'timeout_seconds' => 3,
            ],
        ]);

        $this->runPass($container);

        $checker = $container->findDefinition('healthcheck.checker.http_client.upstream');
        self::assertSame(HttpClientChecker::class, $checker->getClass());
        self::assertSame('upstream', $checker->getArgument(1));

        $target = $checker->getArgument(2);
        self::assertInstanceOf(Definition::class, $target);
        self::assertSame(HttpProbeTarget::class, $target->getClass());
        self::assertSame('https://api.example.com/health', $target->getArgument(0));
        self::assertSame('GET', $target->getArgument(1));
        self::assertSame([200], $target->getArgument(2));
        self::assertSame(3, $target->getArgument(3));
    }

    private function runPass(ContainerBuilder $container): void
    {
        $detectorClasses = [
            DBALConnectionDetector::class,
            RabbitMQConnectionDetector::class,
            CacheClientDetector::class,
            CachePoolDetector::class,
            MongoConnectionDetector::class,
            ODMDocumentManagerDetector::class,
            ElasticaClientDetector::class,
            MessengerTransportDetector::class,
            EntityManagerDetector::class,
            DoctrineMigrationsDetector::class,
            FlysystemDetector::class,
            MailerDetector::class,
            OpenSearchDetector::class,
            KafkaDetector::class,
            ClickHouseDetector::class,
            LockStoreDetector::class,
            HttpClientTargetDetector::class,
        ];
        foreach ($detectorClasses as $class) {
            $container->setDefinition($class, new Definition($class)->addTag(CheckerDetectorInterface::TAG));
        }

        new HealthCheckerAutoDetectionPass()->process($container);
    }
}
