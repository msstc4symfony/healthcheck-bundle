<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\DependencyInjection\Detector;

use Elastica\Client;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\ElasticaConnectionChecker;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final readonly class ElasticaClientDetector implements CheckerDetectorInterface
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

            yield sprintf('healthcheck.checker.%s', $id) => new Definition(ElasticaConnectionChecker::class)
                    ->addArgument(new Reference($id))
                    ->addArgument($id)
            ;
        }
    }
}
