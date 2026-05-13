<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\DependencyInjection\Detector;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\EntityManagerChecker;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final readonly class EntityManagerDetector implements CheckerDetectorInterface
{
    /**
     * @return iterable<string, Definition>
     */
    public function detect(ContainerBuilder $container): iterable
    {
        foreach (array_keys($container->getDefinitions()) as $id) {
            if (preg_match('/^doctrine\.orm\.(\w+)_entity_manager$/Ss', $id, $match) === 1) {
                yield sprintf('healthcheck.checker.%s', $id) => new Definition(EntityManagerChecker::class)
                    ->addArgument(new Reference($id))
                    ->addArgument($match[1])
                ;
            }
        }
    }
}
