<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle;

use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\CacheClientDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\CachePoolDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\DBALConnectionDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\ElasticaClientDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\MongoConnectionDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\ODMDocumentManagerDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\RabbitMQConnectionDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\HealthCheckerAutoDetectionPass;
use MaxShamaev\HealthCheckBundle\DependencyInjection\HealthCheckExtension;
use Override;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class HealthCheckBundle extends Bundle
{
    #[Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new HealthCheckerAutoDetectionPass([
            new DBALConnectionDetector(),
            new RabbitMQConnectionDetector(),
            new CacheClientDetector(),
            new CachePoolDetector(),
            new MongoConnectionDetector(),
            new ODMDocumentManagerDetector(),
            new ElasticaClientDetector(),
        ]));
    }

    #[Override]
    public function getContainerExtension(): ExtensionInterface
    {
        return new HealthCheckExtension();
    }
}
