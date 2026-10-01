<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\DependencyInjection;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\DeferredReadinessCheckerDecorator;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\TimeoutCheckerDecorator;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\HealthCheckerDeferredConstructionPass;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\DependentReadinessCheckerFixture;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\SuccessChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class HealthCheckerDeferredConstructionPassTest extends TestCase
{
    public function testWrapsReadinessOnlyCheckerInDeferredDecorator(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('healthcheck.checker.store', new Definition(DependentReadinessCheckerFixture::class)->addTag(CheckInterface::class));

        new HealthCheckerDeferredConstructionPass()->process($container);

        $outer = $container->getDefinition('healthcheck.checker.store');
        self::assertSame(DeferredReadinessCheckerDecorator::class, $outer->getClass());
        self::assertEquals(new ServiceClosureArgument(new Reference('healthcheck.checker.store.deferred_inner')), $outer->getArgument(0));
        self::assertSame('healthcheck.checker.store', $outer->getArgument(1));
        self::assertTrue($outer->hasTag(CheckInterface::class));

        $inner = $container->getDefinition('healthcheck.checker.store.deferred_inner');
        self::assertSame(DependentReadinessCheckerFixture::class, $inner->getClass());
        self::assertFalse($inner->hasTag(CheckInterface::class), 'inner must not be iterated directly');
    }

    public function testRecognisesReadinessCheckerBehindTimeoutDecorator(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('healthcheck.checker.store.inner', new Definition(DependentReadinessCheckerFixture::class));
        $container->setDefinition('healthcheck.checker.store', new Definition(TimeoutCheckerDecorator::class)
            ->setArguments([new Reference('healthcheck.checker.store.inner'), 2000])
            ->addTag(CheckInterface::class));

        new HealthCheckerDeferredConstructionPass()->process($container);

        self::assertSame(DeferredReadinessCheckerDecorator::class, $container->getDefinition('healthcheck.checker.store')->getClass());
        self::assertSame(TimeoutCheckerDecorator::class, $container->getDefinition('healthcheck.checker.store.deferred_inner')->getClass());
    }

    public function testLeavesCheckersThatMaySupportLivelinessUntouched(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.checker', new Definition(SuccessChecker::class)->addTag(CheckInterface::class));
        $container->setDefinition('app.timed', new Definition(TimeoutCheckerDecorator::class)
            ->setArguments([new Reference('app.checker'), 2000])
            ->addTag(CheckInterface::class));

        new HealthCheckerDeferredConstructionPass()->process($container);

        self::assertSame(SuccessChecker::class, $container->getDefinition('app.checker')->getClass());
        self::assertSame(TimeoutCheckerDecorator::class, $container->getDefinition('app.timed')->getClass());
        self::assertFalse($container->hasDefinition('app.checker.deferred_inner'));
    }
}
