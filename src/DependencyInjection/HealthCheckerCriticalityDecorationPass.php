<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\DependencyInjection;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\NonCriticalCheckerDecorator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Wraps configured non-critical checker service ids in NonCriticalCheckerDecorator,
 * which redirects their errors into warnings (does not fail readiness).
 *
 * Must run AFTER HealthCheckerAutoDetectionPass. May run before or after the timeout pass —
 * the order only changes whether timeout exceedances of non-critical checkers count as warnings.
 */
final readonly class HealthCheckerCriticalityDecorationPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(HealthCheckExtension::PARAM_NON_CRITICAL_CHECKERS)) {
            return;
        }

        /** @var list<string> $ids */
        $ids = (array) $container->getParameter(HealthCheckExtension::PARAM_NON_CRITICAL_CHECKERS);

        foreach ($ids as $id) {
            if (!$container->hasDefinition($id)) {
                continue;
            }

            $inner = $container->getDefinition($id);
            if ($inner->getClass() === NonCriticalCheckerDecorator::class) {
                continue;
            }

            $inner->clearTag(CheckInterface::class);
            $innerId = $id . '.critical_inner';
            $container->setDefinition($innerId, $inner);
            $container->removeDefinition($id);

            $decorator = new Definition(NonCriticalCheckerDecorator::class)
                ->setAutowired(true)
                ->addArgument(new Reference($innerId))
                ->addTag(CheckInterface::class)
            ;

            $container->setDefinition($id, $decorator);
        }
    }
}
