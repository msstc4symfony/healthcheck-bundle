<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Test\Unit\DependencyInjection;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\NonCriticalCheckerDecorator;
use MaxShamaev\HealthCheckBundle\DependencyInjection\HealthCheckerCriticalityDecorationPass;
use MaxShamaev\HealthCheckBundle\DependencyInjection\HealthCheckExtension;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class HealthCheckerCriticalityDecorationPassTest extends TestCase
{
    public function testSkipsWhenConfigMissing(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(
            'healthcheck.checker.cache',
            new Definition(stdClass::class)->addTag(CheckInterface::class),
        );

        new HealthCheckerCriticalityDecorationPass()->process($container);

        self::assertSame(stdClass::class, $container->getDefinition('healthcheck.checker.cache')->getClass());
    }

    public function testWrapsListedCheckers(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(HealthCheckExtension::PARAM_NON_CRITICAL_CHECKERS, [
            'healthcheck.checker.cache',
        ]);
        $container->setDefinition(
            'healthcheck.checker.cache',
            new Definition(stdClass::class)->addTag(CheckInterface::class),
        );
        $container->setDefinition(
            'healthcheck.checker.db',
            new Definition(stdClass::class)->addTag(CheckInterface::class),
        );

        new HealthCheckerCriticalityDecorationPass()->process($container);

        $outer = $container->getDefinition('healthcheck.checker.cache');
        self::assertSame(NonCriticalCheckerDecorator::class, $outer->getClass());
        self::assertArrayHasKey(CheckInterface::class, $outer->getTags());

        $inner = $container->getDefinition('healthcheck.checker.cache.critical_inner');
        self::assertSame(stdClass::class, $inner->getClass());
        self::assertArrayNotHasKey(CheckInterface::class, $inner->getTags());

        // Untouched: db checker is still raw.
        self::assertSame(stdClass::class, $container->getDefinition('healthcheck.checker.db')->getClass());
    }

    public function testSkipsMissingServiceIds(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(HealthCheckExtension::PARAM_NON_CRITICAL_CHECKERS, [
            'healthcheck.checker.does_not_exist',
        ]);

        new HealthCheckerCriticalityDecorationPass()->process($container);

        self::assertFalse($container->hasDefinition('healthcheck.checker.does_not_exist'));
    }
}
