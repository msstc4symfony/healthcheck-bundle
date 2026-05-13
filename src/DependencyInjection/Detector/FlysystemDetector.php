<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\DependencyInjection\Detector;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\FlysystemChecker;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final readonly class FlysystemDetector implements CheckerDetectorInterface
{
    /**
     * @return iterable<string, Definition>
     */
    public function detect(ContainerBuilder $container): iterable
    {
        foreach (array_keys($container->findTaggedServiceIds('flysystem.storage')) as $id) {
            yield sprintf('healthcheck.checker.%s', $id) => new Definition(FlysystemChecker::class)
                ->addArgument(new Reference($id))
                ->addArgument($id)
            ;
        }
    }
}
