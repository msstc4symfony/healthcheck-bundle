<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\DependencyInjection\Detector;

use Doctrine\Migrations\DependencyFactory;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\DoctrineMigrationsChecker;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\DoctrineMigrationsDetector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class DoctrineMigrationsDetectorTest extends TestCase
{
    public function testDetectYieldsCheckerWhenDependencyFactoryRegistered(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(DependencyFactory::class, new Definition(DependencyFactory::class));

        $detected = iterator_to_array(new DoctrineMigrationsDetector()->detect($container));

        $checker = $detected['healthcheck.checker.doctrine_migrations'];
        self::assertSame(DoctrineMigrationsChecker::class, $checker->getClass());
        self::assertEquals(new Reference(DependencyFactory::class), $checker->getArgument(0));
    }

    public function testDetectReturnsEmptyWhenDependencyFactoryMissing(): void
    {
        self::assertSame([], iterator_to_array(new DoctrineMigrationsDetector()->detect(new ContainerBuilder())));
    }
}
