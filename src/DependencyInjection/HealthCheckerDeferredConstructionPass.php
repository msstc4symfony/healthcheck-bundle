<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\DependencyInjection;

use LogicException;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\AbstractReadinessChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\DeferredReadinessCheckerDecorator;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\ElasticaConnectionChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\TimeoutCheckerDecorator;
use Override;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Throwable;

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

    private const string DETECTED_CHECKER_ID_PREFIX = 'healthcheck.checker.';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        foreach (array_keys($container->findTaggedServiceIds(CheckInterface::class)) as $id) {
            if ($id === '') {
                continue;
            }

            $definition = $container->getDefinition($id);
            $checker = $this->unwrapTimeout($container, $definition);
            $checkerClass = $this->checkerClass($container, $checker);
            if ($checkerClass === null || !$this->isReadinessOnly($container, $checker)) {
                continue;
            }

            $definition->clearTag(CheckInterface::class);
            $innerId = $id . '.deferred_inner';
            $container->setDefinition($innerId, $definition);
            $container->removeDefinition($id);

            $container->setDefinition($id, new Definition(DeferredReadinessCheckerDecorator::class)
                ->addArgument(new ServiceClosureArgument(new Reference($innerId)))
                ->addArgument($this->label($container, $checker, $id))
                ->addArgument($checkerClass)
                ->addTag(CheckInterface::class));
        }
    }

    /**
     * @return class-string<CheckInterface>|null
     */
    private function checkerClass(ContainerBuilder $container, Definition $checker): ?string
    {
        $name = $this->reflection($container, $checker)?->getName();

        return $name !== null && is_a($name, CheckInterface::class, true) ? $name : null;
    }

    /**
     * @return ReflectionClass<object>|null
     */
    private function reflection(ContainerBuilder $container, Definition $checker): ?ReflectionClass
    {
        $class = $container->getParameterBag()->resolveValue($checker->getClass());

        return is_string($class) ? $container->getReflectionClass($class, false) : null;
    }

    private function isReadinessOnly(ContainerBuilder $container, Definition $checker): bool
    {
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

    /**
     * The checker's own label() (e.g. "Lock store (x)"), read from a lazy ghost that holds only the
     * scalar constructor arguments: a label never needs the target, and the target is exactly what
     * may fail to construct. Labels that do need more fall back to "<checker class> (<service>)".
     *
     * @param non-empty-string $id
     *
     * @return non-empty-string
     */
    private function label(ContainerBuilder $container, Definition $checker, string $id): string
    {
        $fallback = sprintf('%s (%s)', basename(str_replace('\\', '/', (string) $checker->getClass())), $this->stripCheckerPrefix($id));

        try {
            $label = $this->labelFromGhost($container, $checker);
        } catch (Throwable) {
            return $fallback;
        }

        return $label !== '' ? $label : $fallback;
    }

    private function labelFromGhost(ContainerBuilder $container, Definition $checker): string
    {
        $reflection = $this->reflection($container, $checker);
        if (!$reflection instanceof ReflectionClass || !$reflection->hasMethod('label') || !$reflection->getConstructor() instanceof ReflectionMethod) {
            return '';
        }

        $ghost = $reflection->newLazyGhost(static function (): never {
            throw new LogicException('label() needs more than scalar constructor arguments');
        });

        $arguments = $checker->getArguments();
        foreach ($reflection->getConstructor()->getParameters() as $position => $parameter) {
            $value = $arguments[$position] ?? $arguments['$' . $parameter->getName()] ?? null;
            if (!$parameter->isPromoted() || !is_scalar($value)) {
                continue;
            }

            $reflection->getProperty($parameter->getName())
                ->setRawValueWithoutLazyInitialization($ghost, $container->getParameterBag()->resolveValue($value))
            ;
        }

        $label = $reflection->getMethod('label')->invoke($ghost);

        return is_string($label) ? $label : '';
    }

    private function stripCheckerPrefix(string $id): string
    {
        return str_starts_with($id, self::DETECTED_CHECKER_ID_PREFIX)
            ? substr($id, strlen(self::DETECTED_CHECKER_ID_PREFIX))
            : $id;
    }
}
