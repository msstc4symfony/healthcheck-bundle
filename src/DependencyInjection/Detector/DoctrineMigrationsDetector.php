<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector;

use Doctrine\Migrations\DependencyFactory;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\DoctrineMigrationsChecker;
use Override;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final readonly class DoctrineMigrationsDetector implements CheckerDetectorInterface
{
    /**
     * @return iterable<string, Definition>
     */
    #[Override]
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
