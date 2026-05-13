<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\DependencyInjection\Detector;

use ClickHouseDB\Client;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\ClickHouseChecker;
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
            if ($definition->getClass() === Client::class) {
                yield sprintf('healthcheck.checker.%s', $id) => new Definition(ClickHouseChecker::class)
                    ->addArgument(new Reference($id))
                    ->addArgument($id)
                ;
            }
        }
    }
}
