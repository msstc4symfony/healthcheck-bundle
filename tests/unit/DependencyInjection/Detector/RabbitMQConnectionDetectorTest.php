<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Test\Unit\DependencyInjection\Detector;

use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\RabbitmqChecker;
use MSSTC4PHP\HealthCheckBundle\DependencyInjection\Detector\RabbitMQConnectionDetector;
use PhpAmqpLib\Connection\AbstractConnection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class RabbitMQConnectionDetectorTest extends TestCase
{
    public function testDetectYieldsCheckerForTaggedConnections(): void
    {
        $container = new ContainerBuilder();
        $main = new Definition(AbstractConnection::class);
        $main->addTag('old_sound_rabbit_mq.connection');

        $secondary = new Definition(AbstractConnection::class);
        $secondary->addTag('old_sound_rabbit_mq.connection');

        $container->setDefinition('rabbit.main', $main);
        $container->setDefinition('rabbit.secondary', $secondary);

        $detected = iterator_to_array(new RabbitMQConnectionDetector()->detect($container));

        self::assertSame(
            ['healthcheck.checker.rabbit.main', 'healthcheck.checker.rabbit.secondary'],
            array_keys($detected),
        );

        $checker = $detected['healthcheck.checker.rabbit.main'];
        self::assertSame(RabbitmqChecker::class, $checker->getClass());
        self::assertEquals(new Reference('rabbit.main'), $checker->getArgument(0));
    }

    public function testDetectIgnoresUntaggedConnections(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('rabbit.untagged', new Definition(AbstractConnection::class));

        self::assertSame([], iterator_to_array(new RabbitMQConnectionDetector()->detect($container)));
    }
}
