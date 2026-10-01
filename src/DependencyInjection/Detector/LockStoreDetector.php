<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\LockStoreChecker;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\ServiceClass;
use Override;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Lock\PersistingStoreInterface;

final readonly class LockStoreDetector implements CheckerDetectorInterface
{
    /**
     * @return iterable<string, Definition>
     */
    #[Override]
    public function detect(ContainerBuilder $container): iterable
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!ServiceClass::is($container, $definition, PersistingStoreInterface::class)) {
                continue;
            }

            yield sprintf('healthcheck.checker.%s', $id) => new Definition(LockStoreChecker::class)
                ->addArgument(new Reference($id))
                ->addArgument($id)
            ;
        }
    }
}
