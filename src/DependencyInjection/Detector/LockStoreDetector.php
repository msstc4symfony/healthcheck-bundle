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

/**
 * Probes the stores the application uses: every store FrameworkBundle builds for a configured
 * `framework.lock` resource carries the "lock.store" tag, and stores the application registers
 * itself have public-style ids. Untagged hidden stores are FrameworkBundle internals, such as the
 * ".lock.flock.store" / ".lock.semaphore.store" predefined since 8.1 whether used or not.
 */
final readonly class LockStoreDetector implements CheckerDetectorInterface
{
    private const string FRAMEWORK_STORE_TAG = 'lock.store';

    /**
     * @return iterable<string, Definition>
     */
    #[Override]
    public function detect(ContainerBuilder $container): iterable
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if (str_starts_with($id, '.') && !$definition->hasTag(self::FRAMEWORK_STORE_TAG)) {
                continue;
            }

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
