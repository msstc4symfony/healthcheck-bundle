<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Test\Unit\DependencyInjection\Detector;

use Doctrine\DBAL\Connection;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\DBALConnectionChecker;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\DBALConnectionDetector;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class DBALConnectionDetectorTest extends TestCase
{
    public function testDetectYieldsCheckerForEachMatchingConnection(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('doctrine.dbal.default_connection', new Definition(Connection::class));
        $container->setDefinition('doctrine.dbal.reporting_connection', new Definition(Connection::class));

        $detected = iterator_to_array(new DBALConnectionDetector()->detect($container));

        self::assertSame(
            ['healthcheck.checker.doctrine.dbal.default_connection', 'healthcheck.checker.doctrine.dbal.reporting_connection'],
            array_keys($detected),
        );

        $default = $detected['healthcheck.checker.doctrine.dbal.default_connection'];
        self::assertSame(DBALConnectionChecker::class, $default->getClass());
        self::assertEquals(new Reference('doctrine.dbal.default_connection'), $default->getArgument(0));
        self::assertSame('default', $default->getArgument(1));

        self::assertSame('reporting', $detected['healthcheck.checker.doctrine.dbal.reporting_connection']->getArgument(1));
    }

    public function testDetectIgnoresUnrelatedServices(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('doctrine.dbal.foo', new Definition(Connection::class));            // missing _connection suffix
        $container->setDefinition('doctrine.dbal._connection', new Definition(Connection::class));   // empty name capture
        $container->setDefinition('unrelated.service', new Definition(stdClass::class));

        $detected = iterator_to_array(new DBALConnectionDetector()->detect($container));

        self::assertSame([], $detected);
    }

    public function testDetectReturnsEmptyOnEmptyContainer(): void
    {
        self::assertSame([], iterator_to_array(new DBALConnectionDetector()->detect(new ContainerBuilder())));
    }
}
