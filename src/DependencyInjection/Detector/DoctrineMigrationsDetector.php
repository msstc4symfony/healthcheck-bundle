<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\DependencyInjection\Detector;

use Doctrine\Migrations\DependencyFactory;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\DoctrineMigrationsChecker;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final readonly class DoctrineMigrationsDetector implements CheckerDetectorInterface
{
    /**
     * @return iterable<string, Definition>
     */
    public function detect(ContainerBuilder $container): iterable
    {
        if (!$container->has(DependencyFactory::class)) {
            return;
        }

        yield 'healthcheck.checker.doctrine_migrations' => new Definition(DoctrineMigrationsChecker::class)
            ->addArgument(new Reference(DependencyFactory::class))
        ;
    }
}
