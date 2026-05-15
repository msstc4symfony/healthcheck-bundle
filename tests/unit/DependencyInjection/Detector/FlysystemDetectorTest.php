<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Test\Unit\DependencyInjection\Detector;

use League\Flysystem\Filesystem;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\FlysystemChecker;
use MSSTC4PHP\HealthCheckBundle\DependencyInjection\Detector\FlysystemDetector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class FlysystemDetectorTest extends TestCase
{
    public function testDetectYieldsCheckerForTaggedStorage(): void
    {
        $container = new ContainerBuilder();
        $fs = new Definition(Filesystem::class);
        $fs->addTag('flysystem.storage');

        $container->setDefinition('uploads.storage', $fs);

        $detected = iterator_to_array(new FlysystemDetector()->detect($container));

        $checker = $detected['healthcheck.checker.uploads.storage'];
        self::assertSame(FlysystemChecker::class, $checker->getClass());
        self::assertEquals(new Reference('uploads.storage'), $checker->getArgument(0));
        self::assertSame('uploads.storage', $checker->getArgument(1));
    }

    public function testDetectIgnoresUntaggedStorages(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('uploads.untagged', new Definition(Filesystem::class));

        self::assertSame([], iterator_to_array(new FlysystemDetector()->detect($container)));
    }
}
