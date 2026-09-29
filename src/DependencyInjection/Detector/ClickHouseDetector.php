<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector;

use ClickHouseDB\Client;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\ClickHouseChecker;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final readonly class ClickHouseDetector implements CheckerDetectorInterface
{
    /**
     * @return iterable<string, Definition>
     */
    public function detect(ContainerBuilder $container): iterable
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();
            if ($class === null || ($class !== Client::class && !is_subclass_of($class, Client::class))) {
                continue;
            }

            yield sprintf('healthcheck.checker.%s', $id) => new Definition(ClickHouseChecker::class)
                ->addArgument(new Reference($id))
                ->addArgument($id)
            ;
        }
    }
}
