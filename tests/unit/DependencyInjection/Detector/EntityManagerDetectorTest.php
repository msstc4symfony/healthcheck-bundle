<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Test\Unit\DependencyInjection\Detector;

use Doctrine\ORM\EntityManagerInterface;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\EntityManagerChecker;
use MSSTC4PHP\HealthCheckBundle\DependencyInjection\Detector\EntityManagerDetector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class EntityManagerDetectorTest extends TestCase
{
    public function testDetectYieldsCheckerForMatchingEntityManager(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('doctrine.orm.default_entity_manager', new Definition(EntityManagerInterface::class));
        $container->setDefinition('doctrine.orm.reporting_entity_manager', new Definition(EntityManagerInterface::class));

        $detected = iterator_to_array(new EntityManagerDetector()->detect($container));

        self::assertSame(
            ['healthcheck.checker.doctrine.orm.default_entity_manager', 'healthcheck.checker.doctrine.orm.reporting_entity_manager'],
            array_keys($detected),
        );

        $default = $detected['healthcheck.checker.doctrine.orm.default_entity_manager'];
        self::assertSame(EntityManagerChecker::class, $default->getClass());
        self::assertEquals(new Reference('doctrine.orm.default_entity_manager'), $default->getArgument(0));
        self::assertSame('default', $default->getArgument(1));

        self::assertSame('reporting', $detected['healthcheck.checker.doctrine.orm.reporting_entity_manager']->getArgument(1));
    }

    public function testDetectIgnoresNonMatchingIds(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('doctrine.orm.something_else', new Definition(EntityManagerInterface::class));
        $container->setDefinition('doctrine.orm._entity_manager', new Definition(EntityManagerInterface::class));

        self::assertSame([], iterator_to_array(new EntityManagerDetector()->detect($container)));
    }
}
