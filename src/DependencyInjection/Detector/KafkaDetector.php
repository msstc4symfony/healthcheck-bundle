<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\KafkaChecker;
use Override;
use RdKafka\KafkaConsumer;
use RdKafka\Producer;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final readonly class KafkaDetector implements CheckerDetectorInterface
{
    /**
     * @return iterable<string, Definition>
     */
    #[Override]
    public function detect(ContainerBuilder $container): iterable
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();
            if ($class === null) {
                continue;
            }

            $matches = $class === Producer::class
                || $class === KafkaConsumer::class
                || is_subclass_of($class, Producer::class)
                || is_subclass_of($class, KafkaConsumer::class);
            if (!$matches) {
                continue;
            }

            yield sprintf('healthcheck.checker.%s', $id) => new Definition(KafkaChecker::class)
                ->addArgument(new Reference($id))
                ->addArgument($id)
            ;
        }
    }
}
