<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\DependencyInjection\Detector;

use Elastica\Client;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\ElasticaConnectionChecker;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\ElasticaClientDetector;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Elastica\SubclassedClient;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ChildDefinition;
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

    public function testDetectResolvesClassThroughAbstractPrototype(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('fos_elastica.client_prototype', new Definition(SubclassedClient::class)->setAbstract(true));
        $container->setDefinition('fos_elastica.client.default', new ChildDefinition('fos_elastica.client_prototype'));

        $detected = iterator_to_array(new ElasticaClientDetector()->detect($container));

        self::assertSame(['healthcheck.checker.fos_elastica.client.default'], array_keys($detected));
    }

    public function testDetectResolvesClassThroughParentChain(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('base', new Definition(SubclassedClient::class)->setAbstract(true));
        $container->setDefinition('mid', new ChildDefinition('base'));
        $container->setDefinition('leaf', new ChildDefinition('mid'));

        self::assertContains('healthcheck.checker.leaf', array_keys(iterator_to_array(new ElasticaClientDetector()->detect($container))));
    }

    public function testDetectIgnoresAbstractPrototypeItself(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('fos_elastica.client_prototype', new Definition(SubclassedClient::class)->setAbstract(true));

        self::assertSame([], iterator_to_array(new ElasticaClientDetector()->detect($container)));
    }

    public function testDetectIgnoresChildOfMissingOrCyclicParent(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('orphan', new ChildDefinition('missing'));
        $container->setDefinition('a', new ChildDefinition('b'));
        $container->setDefinition('b', new ChildDefinition('a'));

        self::assertSame([], iterator_to_array(new ElasticaClientDetector()->detect($container)));
    }
}
