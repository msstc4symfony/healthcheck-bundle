<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\MongoConnectionChecker;
use Override;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final readonly class MongoConnectionDetector implements CheckerDetectorInterface
{
    /**
     * @return iterable<string, Definition>
     */
    #[Override]
    public function detect(ContainerBuilder $container): iterable
    {
        foreach (array_keys($container->getDefinitions()) as $id) {
            if (preg_match('/^doctrine_mongodb\.odm\.(\w+)_connection$/Ss', $id, $match) === 1) {
                yield sprintf('healthcheck.checker.%s', $id) => new Definition(MongoConnectionChecker::class)
                        ->addArgument(new Reference($id))
                        ->addArgument($match[1])
                ;
            }
        }
    }
}
