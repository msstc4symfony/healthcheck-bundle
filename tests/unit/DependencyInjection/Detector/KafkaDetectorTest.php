<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\DependencyInjection\Detector;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\KafkaChecker;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\KafkaDetector;
use PHPUnit\Framework\TestCase;
use RdKafka\KafkaConsumer;
use RdKafka\Producer;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class KafkaDetectorTest extends TestCase
{
    public function testDetectYieldsCheckerForKafkaProducer(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('kafka.producer', new Definition(Producer::class));

        $detected = iterator_to_array(new KafkaDetector()->detect($container));

        $checker = $detected['healthcheck.checker.kafka.producer'];
        self::assertSame(KafkaChecker::class, $checker->getClass());
        self::assertEquals(new Reference('kafka.producer'), $checker->getArgument(0));
        self::assertSame('kafka.producer', $checker->getArgument(1));
    }

    public function testDetectYieldsCheckerForKafkaConsumer(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('kafka.consumer', new Definition(KafkaConsumer::class));

        $detected = iterator_to_array(new KafkaDetector()->detect($container));

        self::assertSame(KafkaChecker::class, $detected['healthcheck.checker.kafka.consumer']->getClass());
    }

    public function testDetectIgnoresUnrelatedAndClasslessDefinitions(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.other', new Definition(stdClass::class));
        $container->setDefinition('app.classless', new Definition());

        self::assertSame([], iterator_to_array(new KafkaDetector()->detect($container)));
    }
}
