<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Test\Unit\DependencyInjection;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\Action;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\ActionInterface;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\CachedActionDecorator;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\ParallelAction;
use MaxShamaev\HealthCheckBundle\DependencyInjection\HealthCheckExtension;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class HealthCheckExtensionTest extends TestCase
{
    public function testDefaultAliasPointsAtAction(): void
    {
        $container = $this->buildContainer();

        new HealthCheckExtension()->load([], $container);

        $alias = $container->getAlias(ActionInterface::class);
        self::assertInstanceOf(Alias::class, $alias);
        self::assertSame(Action::class, (string) $alias);
    }

    public function testParallelExecutionRegistersAndAliasesParallelAction(): void
    {
        $container = $this->buildContainer();

        new HealthCheckExtension()->load([['execution' => 'parallel']], $container);

        self::assertTrue($container->hasDefinition(HealthCheckExtension::SERVICE_PARALLEL_ACTION));
        self::assertSame(
            ParallelAction::class,
            $container->getDefinition(HealthCheckExtension::SERVICE_PARALLEL_ACTION)->getClass(),
        );

        $alias = $container->getAlias(ActionInterface::class);
        self::assertSame(HealthCheckExtension::SERVICE_PARALLEL_ACTION, (string) $alias);
    }

    public function testCacheEnabledAliasesCachedDecoratorOverInner(): void
    {
        $container = $this->buildContainer();
        $container->setDefinition('cache.app', new Definition(stdClass::class));

        new HealthCheckExtension()->load([
            ['cache' => ['enabled' => true, 'pool' => 'cache.app']],
        ], $container);

        self::assertTrue($container->hasDefinition(HealthCheckExtension::SERVICE_CACHED_ACTION));
        $cached = $container->getDefinition(HealthCheckExtension::SERVICE_CACHED_ACTION);
        self::assertSame(CachedActionDecorator::class, $cached->getClass());

        $alias = $container->getAlias(ActionInterface::class);
        self::assertSame(HealthCheckExtension::SERVICE_CACHED_ACTION, (string) $alias);
    }

    public function testCacheEnabledWithParallelStacksDecoratorsCorrectly(): void
    {
        $container = $this->buildContainer();
        $container->setDefinition('cache.app', new Definition(stdClass::class));

        new HealthCheckExtension()->load([
            ['execution' => 'parallel', 'cache' => ['enabled' => true, 'pool' => 'cache.app']],
        ], $container);

        // Inner is ParallelAction, outer alias is CachedActionDecorator.
        self::assertTrue($container->hasDefinition(HealthCheckExtension::SERVICE_PARALLEL_ACTION));
        self::assertTrue($container->hasDefinition(HealthCheckExtension::SERVICE_CACHED_ACTION));

        $cached = $container->getDefinition(HealthCheckExtension::SERVICE_CACHED_ACTION);
        self::assertSame(CachedActionDecorator::class, $cached->getClass());

        // First constructor arg of the cached decorator is a Reference to the parallel service.
        $innerReference = $cached->getArgument(0);
        self::assertInstanceOf(Reference::class, $innerReference);
        self::assertSame(HealthCheckExtension::SERVICE_PARALLEL_ACTION, (string) $innerReference);

        $alias = $container->getAlias(ActionInterface::class);
        self::assertSame(HealthCheckExtension::SERVICE_CACHED_ACTION, (string) $alias);
    }

    private function buildContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        // The PSR-4 service loader normally registers Action; emulate that here.
        $container->setDefinition(Action::class, new Definition(Action::class)->setAutowired(true));

        return $container;
    }
}
