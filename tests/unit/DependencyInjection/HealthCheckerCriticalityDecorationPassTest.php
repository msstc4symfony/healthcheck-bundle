<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\DependencyInjection;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\NonCriticalCheckerDecorator;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\TimeoutCheckerDecorator;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\HealthCheckerCriticalityDecorationPass;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\HealthCheckerTimeoutDecorationPass;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\HealthCheckExtension;
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

    public function testTimeoutThenCriticalityProducesCorrectComposition(): void
    {
        // HealthCheckBundle::build() registers Timeout pass BEFORE Criticality pass so the final
        // composition is NonCritical(Timeout(inner)) — that order makes "non-critical timeout"
        // surface as a warning, not an error. This test locks in that wiring.
        $container = new ContainerBuilder();
        $container->setParameter(HealthCheckExtension::PARAM_DEFAULT_TIMEOUT_MS, 2000);
        $container->setParameter(HealthCheckExtension::PARAM_TIMEOUT_OVERRIDES, []);
        $container->setParameter(HealthCheckExtension::PARAM_NON_CRITICAL_CHECKERS, [
            'healthcheck.checker.cache',
        ]);

        $container->setDefinition(
            'healthcheck.checker.cache',
            new Definition(stdClass::class)->addTag(CheckInterface::class),
        );

        new HealthCheckerTimeoutDecorationPass()->process($container);
        new HealthCheckerCriticalityDecorationPass()->process($container);

        // Outermost: NonCriticalCheckerDecorator
        $outer = $container->getDefinition('healthcheck.checker.cache');
        self::assertSame(NonCriticalCheckerDecorator::class, $outer->getClass());

        // Middle: TimeoutCheckerDecorator (registered by the Criticality pass under .critical_inner)
        $middle = $container->getDefinition('healthcheck.checker.cache.critical_inner');
        self::assertSame(TimeoutCheckerDecorator::class, $middle->getClass());

        // Innermost: the original stdClass checker (registered by the Timeout pass under .inner)
        $inner = $container->getDefinition('healthcheck.checker.cache.inner');
        self::assertSame(stdClass::class, $inner->getClass());
    }
}
