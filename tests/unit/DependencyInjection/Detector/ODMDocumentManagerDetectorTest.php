<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\DependencyInjection\Detector;

use Doctrine\ODM\MongoDB\DocumentManager;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\ODMConnectionChecker;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\ODMDocumentManagerDetector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class ODMDocumentManagerDetectorTest extends TestCase
{
    public function testDetectYieldsCheckerForMatchingDocumentManager(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(
            'doctrine_mongodb.odm.default_document_manager',
            new Definition(DocumentManager::class),
        );

        $detected = iterator_to_array(new ODMDocumentManagerDetector()->detect($container));

        $checker = $detected['healthcheck.checker.doctrine_mongodb.odm.default_document_manager'];
        self::assertSame(ODMConnectionChecker::class, $checker->getClass());
        self::assertEquals(new Reference('doctrine_mongodb.odm.default_document_manager'), $checker->getArgument(0));
        self::assertSame('default', $checker->getArgument(1));
    }

    public function testDetectIgnoresNonMatchingIds(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('doctrine_mongodb.odm.something_else', new Definition(DocumentManager::class));
        $container->setDefinition('doctrine_mongodb.odm.default_connection', new Definition(DocumentManager::class));

        self::assertSame([], iterator_to_array(new ODMDocumentManagerDetector()->detect($container)));
    }
}
