<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Test\Unit\DependencyInjection\Detector;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\OpenSearchChecker;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\OpenSearchDetector;
use OpenSearch\Client;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class OpenSearchDetectorTest extends TestCase
{
    public function testDetectYieldsCheckerForOpenSearchClient(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('opensearch.client.main', new Definition(Client::class));

        $detected = iterator_to_array(new OpenSearchDetector()->detect($container));

        $checker = $detected['healthcheck.checker.opensearch.client.main'];
        self::assertSame(OpenSearchChecker::class, $checker->getClass());
        self::assertEquals(new Reference('opensearch.client.main'), $checker->getArgument(0));
        self::assertSame('opensearch.client.main', $checker->getArgument(1));
    }

    public function testDetectIgnoresUnrelatedAndClasslessDefinitions(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.other', new Definition(stdClass::class));
        $container->setDefinition('app.classless', new Definition());

        self::assertSame([], iterator_to_array(new OpenSearchDetector()->detect($container)));
    }
}
