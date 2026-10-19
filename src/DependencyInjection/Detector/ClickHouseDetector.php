<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector;

use ClickHouseDB\Client;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\ServiceClass;
use Msstc4Symfony\HealthCheckBundle\Infrastructure\Checker\ClickHouseChecker;
use Override;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
final readonly class ClickHouseDetector implements CheckerDetectorInterface
{
    /**
     * @return iterable<string, Definition>
     */
    #[Override]
    public function detect(ContainerBuilder $container): iterable
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!ServiceClass::is($container, $definition, Client::class)) {
                continue;
            }

            yield sprintf('healthcheck.checker.%s', $id) => (new Definition(ClickHouseChecker::class))
                ->addArgument(new Reference($id))
                ->addArgument($id)
            ;
        }
    }
}
