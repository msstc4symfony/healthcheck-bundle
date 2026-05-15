<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Test\Unit\DependencyInjection\Detector;

use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\MongoConnectionChecker;
use MSSTC4PHP\HealthCheckBundle\DependencyInjection\Detector\MongoConnectionDetector;
use MongoDB\Client;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class MongoConnectionDetectorTest extends TestCase
{
    public function testDetectYieldsCheckerForMatchingConnection(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('doctrine_mongodb.odm.default_connection', new Definition(Client::class));

        $detected = iterator_to_array(new MongoConnectionDetector()->detect($container));

        $checker = $detected['healthcheck.checker.doctrine_mongodb.odm.default_connection'];
        self::assertSame(MongoConnectionChecker::class, $checker->getClass());
        self::assertEquals(new Reference('doctrine_mongodb.odm.default_connection'), $checker->getArgument(0));
        self::assertSame('default', $checker->getArgument(1));
    }

    public function testDetectIgnoresNonMatchingIds(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('doctrine_mongodb.something_else', new Definition(Client::class));
        $container->setDefinition('doctrine_mongodb.odm.default_document_manager', new Definition(Client::class));

        self::assertSame([], iterator_to_array(new MongoConnectionDetector()->detect($container)));
    }
}
