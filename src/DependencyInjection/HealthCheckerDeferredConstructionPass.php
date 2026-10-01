<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\DependencyInjection;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\AbstractReadinessChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\DeferredReadinessCheckerDecorator;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\ElasticaConnectionChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\TimeoutCheckerDecorator;
use Override;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Wraps every readiness-only checker in DeferredReadinessCheckerDecorator so that constructing
 * its target happens inside the readiness probe, never while liveliness iterates the checkers.
 *
 * Runs AFTER the timeout pass (the timeout message names the real checker class) and BEFORE the
 * criticality pass (a construction failure of a non-critical checker stays a warning):
 * NonCritical( Deferred( Timeout( inner ) ) ).
 *
 * @internal
 */
final readonly class HealthCheckerDeferredConstructionPass implements CompilerPassInterface
{
    /**
     * Checker types whose isSupport() accepts readiness only.
     */
    private const array READINESS_ONLY_TYPES = [
        AbstractReadinessChecker::class,
        ElasticaConnectionChecker::class,
    ];

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        foreach (array_keys($container->findTaggedServiceIds(CheckInterface::class)) as $id) {
            if ($id === '') {
                continue;
            }

            $definition = $container->getDefinition($id);
            if (!$this->isReadinessOnly($container, $definition)) {
                continue;
            }

            $definition->clearTag(CheckInterface::class);
            $innerId = $id . '.deferred_inner';
            $container->setDefinition($innerId, $definition);
            $container->removeDefinition($id);

            $container->setDefinition($id, new Definition(DeferredReadinessCheckerDecorator::class)
                ->addArgument(new ServiceClosureArgument(new Reference($innerId)))
                ->addArgument($id)
                ->addTag(CheckInterface::class));
        }
    }

    private function isReadinessOnly(ContainerBuilder $container, Definition $definition): bool
    {
        $checker = $this->unwrapTimeout($container, $definition);

        return array_any(
            self::READINESS_ONLY_TYPES,
            static fn (string $type): bool => ServiceClass::is($container, $checker, $type),
        );
    }

    private function unwrapTimeout(ContainerBuilder $container, Definition $definition): Definition
    {
        if ($definition->getClass() !== TimeoutCheckerDecorator::class) {
            return $definition;
        }

        $inner = $definition->getArguments()[0] ?? null;

        return $inner instanceof Reference && $container->has((string) $inner)
            ? $container->findDefinition((string) $inner)
            : $definition;
    }
}
