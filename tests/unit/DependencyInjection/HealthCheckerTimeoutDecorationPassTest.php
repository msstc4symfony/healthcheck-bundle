<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Test\Unit\DependencyInjection;

use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Action;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\TimeoutCheckerDecorator;
use MSSTC4PHP\HealthCheckBundle\DependencyInjection\HealthCheckerTimeoutDecorationPass;
use MSSTC4PHP\HealthCheckBundle\DependencyInjection\HealthCheckExtension;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class HealthCheckerTimeoutDecorationPassTest extends TestCase
{
    public function testSkipsWhenConfigParamNotSet(): void
    {
        $container = new ContainerBuilder();
        $inner = new Definition(Action::class)->addTag(CheckInterface::class);
        $container->setDefinition('healthcheck.checker.foo', $inner);

        new HealthCheckerTimeoutDecorationPass()->process($container);

        self::assertSame(Action::class, $container->getDefinition('healthcheck.checker.foo')->getClass());
        self::assertFalse($container->hasDefinition('healthcheck.checker.foo.inner'));
    }

    public function testWrapsAllTaggedCheckersWithDefaultTimeout(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(HealthCheckExtension::PARAM_DEFAULT_TIMEOUT_MS, 2000);
        $container->setParameter(HealthCheckExtension::PARAM_TIMEOUT_OVERRIDES, []);

        $a = new Definition(stdClass::class)->addTag(CheckInterface::class);
        $b = new Definition(stdClass::class)->addTag(CheckInterface::class);
        $container->setDefinition('healthcheck.checker.a', $a);
        $container->setDefinition('healthcheck.checker.b', $b);

        new HealthCheckerTimeoutDecorationPass()->process($container);

        foreach (['a', 'b'] as $name) {
            $outer = $container->getDefinition('healthcheck.checker.' . $name);
            self::assertSame(TimeoutCheckerDecorator::class, $outer->getClass());
            self::assertSame(2000, $outer->getArgument(1));
            self::assertArrayHasKey(CheckInterface::class, $outer->getTags());

            $inner = $container->getDefinition('healthcheck.checker.' . $name . '.inner');
            self::assertSame(stdClass::class, $inner->getClass());
            self::assertArrayNotHasKey(CheckInterface::class, $inner->getTags(), 'inner must not be re-detected by the iterator');
        }
    }

    public function testAppliesPerCheckerOverride(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(HealthCheckExtension::PARAM_DEFAULT_TIMEOUT_MS, 2000);
        $container->setParameter(HealthCheckExtension::PARAM_TIMEOUT_OVERRIDES, [
            'healthcheck.checker.slow' => 10_000,
        ]);

        $container->setDefinition(
            'healthcheck.checker.slow',
            new Definition(stdClass::class)->addTag(CheckInterface::class),
        );
        $container->setDefinition(
            'healthcheck.checker.fast',
            new Definition(stdClass::class)->addTag(CheckInterface::class),
        );

        new HealthCheckerTimeoutDecorationPass()->process($container);

        self::assertSame(10_000, $container->getDefinition('healthcheck.checker.slow')->getArgument(1));
        self::assertSame(2000, $container->getDefinition('healthcheck.checker.fast')->getArgument(1));
    }

    public function testIsIdempotent(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(HealthCheckExtension::PARAM_DEFAULT_TIMEOUT_MS, 2000);
        $container->setParameter(HealthCheckExtension::PARAM_TIMEOUT_OVERRIDES, []);

        $container->setDefinition(
            'healthcheck.checker.x',
            new Definition(stdClass::class)->addTag(CheckInterface::class),
        );

        $pass = new HealthCheckerTimeoutDecorationPass();
        $pass->process($container);
        $pass->process($container);

        self::assertSame(TimeoutCheckerDecorator::class, $container->getDefinition('healthcheck.checker.x')->getClass());
        self::assertFalse($container->hasDefinition('healthcheck.checker.x.inner.inner'));
    }
}
