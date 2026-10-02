<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\DependencyInjection;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\DeferredReadinessCheckerDecorator;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\ElasticaConnectionChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\LockStoreChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\TimeoutCheckerDecorator;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\HealthCheckerDeferredConstructionPass;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\DependentReadinessCheckerFixture;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\SuccessChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
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
        self::assertSame('Dependent', $outer->getArgument(1));
        self::assertSame(DependentReadinessCheckerFixture::class, $outer->getArgument(2));
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
        self::assertSame(DependentReadinessCheckerFixture::class, $container->getDefinition('healthcheck.checker.store')->getArgument(2));
    }

    public function testResolvesTheClassOfAChildDefinitionThroughItsParents(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.checker.base', new Definition(DependentReadinessCheckerFixture::class)->setAbstract(true));
        $container->setDefinition('app.checker.template', new ChildDefinition('app.checker.base')->setAbstract(true));
        $container->setDefinition('app.checker.inner', new ChildDefinition('app.checker.template'));
        $container->setDefinition('app.checker', new Definition(TimeoutCheckerDecorator::class)
            ->setArguments([new Reference('app.checker.inner'), 2000])
            ->addTag(CheckInterface::class));

        new HealthCheckerDeferredConstructionPass()->process($container);

        $outer = $container->getDefinition('app.checker');
        self::assertSame(DeferredReadinessCheckerDecorator::class, $outer->getClass());
        self::assertSame('Dependent', $outer->getArgument(1));
        self::assertSame(DependentReadinessCheckerFixture::class, $outer->getArgument(2));
    }

    public function testLabelsAChildDefinitionWithItsOwnReplacedArguments(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.lock.template', new Definition(LockStoreChecker::class, [new Reference('app.store'), 'parent-name'])->setAbstract(true));
        $container->setDefinition('app.lock', new ChildDefinition('app.lock.template')
            ->replaceArgument(1, 'child-name')
            ->addTag(CheckInterface::class));

        new HealthCheckerDeferredConstructionPass()->process($container);

        self::assertSame('Lock store (child-name)', $container->getDefinition('app.lock')->getArgument(1));
    }

    public function testLabelsAChildDefinitionWhoseNamedArgumentReplacesAPositionalParentArgument(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.lock.template', new Definition(LockStoreChecker::class, [new Reference('app.store'), 'parent-name'])->setAbstract(true));
        $container->setDefinition('app.lock', new ChildDefinition('app.lock.template')
            ->setArgument('$name', 'child-name')
            ->addTag(CheckInterface::class));

        new HealthCheckerDeferredConstructionPass()->process($container);

        self::assertSame('Lock store (child-name)', $container->getDefinition('app.lock')->getArgument(1));
    }

    /**
     * ResolveChildDefinitionsPass reports the cycle later; this pass must not loop over it.
     */
    public function testLeavesAChildDefinitionWithCircularParentsUntouched(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.a', new ChildDefinition('app.b')->addTag(CheckInterface::class));
        $container->setDefinition('app.b', new ChildDefinition('app.a'));

        new HealthCheckerDeferredConstructionPass()->process($container);

        self::assertInstanceOf(ChildDefinition::class, $container->getDefinition('app.a'));
        self::assertFalse($container->hasDefinition('app.a.deferred_inner'));
    }

    public function testLeavesAChildDefinitionWithAMissingParentUntouched(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.checker', new ChildDefinition('app.missing')->addTag(CheckInterface::class));

        new HealthCheckerDeferredConstructionPass()->process($container);

        self::assertInstanceOf(ChildDefinition::class, $container->getDefinition('app.checker'));
        self::assertFalse($container->hasDefinition('app.checker.deferred_inner'));
    }

    public function testChildDefinitionClassOverridesItsParents(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.checker.template', new Definition(DependentReadinessCheckerFixture::class)->setAbstract(true));
        $container->setDefinition('app.checker', new ChildDefinition('app.checker.template')
            ->setClass(SuccessChecker::class)
            ->addTag(CheckInterface::class));

        new HealthCheckerDeferredConstructionPass()->process($container);

        self::assertInstanceOf(ChildDefinition::class, $container->getDefinition('app.checker'));
        self::assertFalse($container->hasDefinition('app.checker.deferred_inner'));
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

    public function testLabelsFailuresLikeTheCheckerItselfWithoutBuildingTheTarget(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('healthcheck.checker..lock.default.store.abc.inner', new Definition(LockStoreChecker::class)
            ->setArguments([new Reference('.lock.default.store.abc'), '.lock.default.store.abc']));
        $container->setDefinition('healthcheck.checker..lock.default.store.abc', new Definition(TimeoutCheckerDecorator::class)
            ->setArguments([new Reference('healthcheck.checker..lock.default.store.abc.inner'), 2000])
            ->addTag(CheckInterface::class));

        new HealthCheckerDeferredConstructionPass()->process($container);

        self::assertSame('Lock store (.lock.default.store.abc)', $container->getDefinition('healthcheck.checker..lock.default.store.abc')->getArgument(1));
    }

    public function testFallsBackToCheckerClassAndServiceWhenLabelIsUnavailable(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('healthcheck.checker.app.lock', new Definition(LockStoreChecker::class)
            ->setArguments([new Reference('app.lock'), '%missing.parameter%'])
            ->addTag(CheckInterface::class));

        new HealthCheckerDeferredConstructionPass()->process($container);

        self::assertSame('LockStoreChecker (app.lock)', $container->getDefinition('healthcheck.checker.app.lock')->getArgument(1));
    }

    public function testWrapsElasticaCheckerWithoutTheElasticaPackage(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('healthcheck.checker.elastica.client', new Definition(ElasticaConnectionChecker::class)
            ->setArguments([new Reference('elastica.client'), 'elastica.client'])
            ->addTag(CheckInterface::class));

        new HealthCheckerDeferredConstructionPass()->process($container);

        self::assertSame(DeferredReadinessCheckerDecorator::class, $container->getDefinition('healthcheck.checker.elastica.client')->getClass());
    }

    public function testLeavesTimeoutDecoratorWhoseInnerServiceIsMissing(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.timed', new Definition(TimeoutCheckerDecorator::class)
            ->setArguments([new Reference('app.missing'), 2000])
            ->addTag(CheckInterface::class));

        new HealthCheckerDeferredConstructionPass()->process($container);

        self::assertSame(TimeoutCheckerDecorator::class, $container->getDefinition('app.timed')->getClass());
    }

    public function testLeavesTimeoutDecoratorWhoseInnerIsNotAReference(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.timed', new Definition(TimeoutCheckerDecorator::class)
            ->setArguments([new Definition(DependentReadinessCheckerFixture::class), 2000])
            ->addTag(CheckInterface::class));

        new HealthCheckerDeferredConstructionPass()->process($container);

        self::assertSame(TimeoutCheckerDecorator::class, $container->getDefinition('app.timed')->getClass());
    }

    public function testDoesNotWrapAnAlreadyDeferredChecker(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('healthcheck.checker.store', new Definition(DependentReadinessCheckerFixture::class)->addTag(CheckInterface::class));

        new HealthCheckerDeferredConstructionPass()->process($container);
        new HealthCheckerDeferredConstructionPass()->process($container);

        self::assertSame(DeferredReadinessCheckerDecorator::class, $container->getDefinition('healthcheck.checker.store')->getClass());
        self::assertSame(DependentReadinessCheckerFixture::class, $container->getDefinition('healthcheck.checker.store.deferred_inner')->getClass());
        self::assertFalse($container->hasDefinition('healthcheck.checker.store.deferred_inner.deferred_inner'));
    }
}
