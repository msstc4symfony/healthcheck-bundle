<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\DependencyInjection;

use Exception;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\CacheChecker;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\DBALConnectionChecker;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\ElasticaConnectionChecker;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\MemcacheChecker;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\MemcachedChecker;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\MongoConnectionChecker;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\PredisChecker;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\RabbitmqChecker;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\RedisChecker;
use Memcache;
use Memcached;
use Predis\Client;
use Redis;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

final class HealthCheckExtension extends Extension implements CompilerPassInterface
{
    /**
     * @param array<array-key, mixed> $configs
     *
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        /** @psalm-suppress ReservedWord */
        $loader->load('services.yaml');
    }

    public function process(ContainerBuilder $container): void
    {
        $this->definedDBALCheckers($container);
        $this->definedOldSoundRabbitCheckers($container);
        $this->definedCacheClientsCheckers($container);
        $this->definedCachePoolsCheckers($container);
        $this->definedMongoCheckers($container);
        $this->definedElasticaCheckers($container);
    }

    private function definedDBALCheckers(ContainerBuilder $container): void
    {
        // Add health handler for every Doctrine DBAL connection
        foreach (array_keys($container->getDefinitions()) as $id) {
            if (preg_match('/^doctrine.dbal.(\w+)_connection$/Ss', $id, $match) === 1) {
                $name = $match[1];
                $hid = sprintf('healthcheck.checker.%s', $id);
                $handler = new Definition(DBALConnectionChecker::class)
                    ->addArgument(new Reference($id))
                    ->addArgument($name)
                ;
                $this->addDefinition($container, $hid, $handler);
            }
        }
    }

    private function definedOldSoundRabbitCheckers(ContainerBuilder $container): void
    {
        // Add health handler for every RabbitMQ connection
        foreach (array_keys($container->findTaggedServiceIds('old_sound_rabbit_mq.connection')) as $id) {
            $hid = sprintf('healthcheck.checker.%s', $id);
            $handler = new Definition(RabbitmqChecker::class)
                ->addArgument(new Reference($id))
            ;
            $this->addDefinition($container, $hid, $handler);
        }
    }

    private function definedCacheClientsCheckers(ContainerBuilder $container): void
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

            $hid = sprintf('healthcheck.checker.%s', $id);
            $handler = new Definition($checkerClass)
                ->addArgument(new Reference($id))
            ;
            $this->addDefinition($container, $hid, $handler);
        }
    }

    private function definedCachePoolsCheckers(ContainerBuilder $container): void
    {
        // Add health handler for every cache pool
        /** @var list<array{name?: string}> $tags */
        foreach ($container->findTaggedServiceIds('cache.pool') as $id => $tags) {
            $pool = $container->getDefinition($id);
            if ($pool->isAbstract()) {
                continue;
            }
            $class = $pool->getClass();
            $parentName = null;
            while ($pool instanceof ChildDefinition) {
                $pool = $container->findDefinition($pool->getParent());
                $parentName = $pool->getClass();
                $class ??= $pool->getClass();
                $parentTags = $pool->getTag('cache.pool');
                if ($parentTags !== []) {
                    // Child's keys win, parent fills in the missing ones (e.g. inherited "name").
                    $tags[0] = ($tags[0] ?? []) + $parentTags[0];
                }
            }
            $name = $tags[0]['name'] ?? $id;

            if ($class === null) {
                continue;
            }

            $hid = sprintf('healthcheck.checker.cache.pool.%s', $name);
            $handler = new Definition(CacheChecker::class)
                ->addArgument(new Reference($id))
                ->addArgument($name)
                ->addArgument($parentName)
            ;
            $this->addDefinition($container, $hid, $handler);
        }
    }

    private function definedMongoCheckers(ContainerBuilder $container): void
    {
        // Add health handler for every Doctrine ODM connection
        foreach (array_keys($container->getDefinitions()) as $id) {
            if (preg_match('/^doctrine_mongodb.odm.(\w+)_connection$/Ss', $id, $match) === 1) {
                $name = $match[1];
                $hid = sprintf('healthcheck.checker.%s', $id);
                $handler = new Definition(MongoConnectionChecker::class)
                    ->addArgument(new Reference($id))
                    ->addArgument($name)
                ;
                $this->addDefinition($container, $hid, $handler);
            }
        }
    }

    private function definedElasticaCheckers(ContainerBuilder $container): void
    {
        // Add health handler for every Elastica client
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition->getClass() === \Elastica\Client::class) {
                $hid = sprintf('healthcheck.checker.%s', $id);
                $handler = new Definition(ElasticaConnectionChecker::class)
                    ->addArgument(new Reference($id))
                    ->addArgument($id)
                ;
                $this->addDefinition($container, $hid, $handler);
            }
        }
    }

    private function addDefinition(ContainerBuilder $container, string $id, Definition $handler): void
    {
        $handler->setAutowired(true);
        $handler->addTag(CheckInterface::class);

        $container->setDefinition($id, $handler);
    }
}
