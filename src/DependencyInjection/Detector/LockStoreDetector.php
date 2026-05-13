<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\DependencyInjection\Detector;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\LockStoreChecker;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Lock\PersistingStoreInterface;

final readonly class LockStoreDetector implements CheckerDetectorInterface
{
    /**
     * @return iterable<string, Definition>
     */
    public function detect(ContainerBuilder $container): iterable
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();
            if ($class === null) {
                continue;
            }
            if ($class !== PersistingStoreInterface::class && !is_subclass_of($class, PersistingStoreInterface::class)) {
                continue;
            }

            yield sprintf('healthcheck.checker.%s', $id) => new Definition(LockStoreChecker::class)
                ->addArgument(new Reference($id))
                ->addArgument($id)
            ;
        }
    }
}
