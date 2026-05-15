<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Test\Unit\DependencyInjection\Detector;

use ClickHouseDB\Client;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\ClickHouseChecker;
use MSSTC4PHP\HealthCheckBundle\DependencyInjection\Detector\ClickHouseDetector;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class ClickHouseDetectorTest extends TestCase
{
    public function testDetectYieldsCheckerForClickHouseClient(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('clickhouse.analytics', new Definition(Client::class));

        $detected = iterator_to_array(new ClickHouseDetector()->detect($container));

        $checker = $detected['healthcheck.checker.clickhouse.analytics'];
        self::assertSame(ClickHouseChecker::class, $checker->getClass());
        self::assertEquals(new Reference('clickhouse.analytics'), $checker->getArgument(0));
        self::assertSame('clickhouse.analytics', $checker->getArgument(1));
    }

    public function testDetectIgnoresUnrelatedAndClasslessDefinitions(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.other', new Definition(stdClass::class));
        $container->setDefinition('app.classless', new Definition());

        self::assertSame([], iterator_to_array(new ClickHouseDetector()->detect($container)));
    }
}
