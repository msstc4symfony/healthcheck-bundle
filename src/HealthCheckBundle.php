<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle;

use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\CacheClientDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\CachePoolDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\ClickHouseDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\DBALConnectionDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\DoctrineMigrationsDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\ElasticaClientDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\EntityManagerDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\FlysystemDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\HttpClientTargetDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\KafkaDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\LockStoreDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\MailerDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\MessengerTransportDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\MongoConnectionDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\ODMDocumentManagerDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\OpenSearchDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\RabbitMQConnectionDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\HealthCheckerAutoDetectionPass;
use MaxShamaev\HealthCheckBundle\DependencyInjection\HealthCheckerCriticalityDecorationPass;
use MaxShamaev\HealthCheckBundle\DependencyInjection\HealthCheckerTimeoutDecorationPass;
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
            new MessengerTransportDetector(),
            new EntityManagerDetector(),
            new DoctrineMigrationsDetector(),
            new FlysystemDetector(),
            new MailerDetector(),
            new OpenSearchDetector(),
            new KafkaDetector(),
            new ClickHouseDetector(),
            new LockStoreDetector(),
            new HttpClientTargetDetector(),
        ]));

        $container->addCompilerPass(new HealthCheckerCriticalityDecorationPass());
        $container->addCompilerPass(new HealthCheckerTimeoutDecorationPass());
    }

    #[Override]
    public function getContainerExtension(): ExtensionInterface
    {
        return new HealthCheckExtension();
    }
}
