<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Test\Unit\DependencyInjection\Detector;

use Elastica\Client;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\ElasticaConnectionChecker;
use MSSTC4PHP\HealthCheckBundle\DependencyInjection\Detector\ElasticaClientDetector;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class ElasticaClientDetectorTest extends TestCase
{
    public function testDetectYieldsCheckerForElasticaClient(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('elastica.client.main', new Definition(Client::class));

        $detected = iterator_to_array(new ElasticaClientDetector()->detect($container));

        $checker = $detected['healthcheck.checker.elastica.client.main'];
        self::assertSame(ElasticaConnectionChecker::class, $checker->getClass());
        self::assertEquals(new Reference('elastica.client.main'), $checker->getArgument(0));
        self::assertSame('elastica.client.main', $checker->getArgument(1));
    }

    public function testDetectIgnoresUnrelatedClasses(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.other', new Definition(stdClass::class));
        $container->setDefinition('app.classless', new Definition());

        self::assertSame([], iterator_to_array(new ElasticaClientDetector()->detect($container)));
    }
}
