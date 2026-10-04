<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\DependencyInjection\Detector;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\MessengerTransportChecker;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\MessengerTransportDetector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class MessengerTransportDetectorTest extends TestCase
{
    public function testDetectStripsMessengerTransportPrefixFromName(): void
    {
        $container = new ContainerBuilder();
        $transport = new Definition(TransportInterface::class);
        $transport->addTag('messenger.receiver');

        $container->setDefinition('messenger.transport.async', $transport);

        $detected = iterator_to_array(new MessengerTransportDetector()->detect($container));

        $checker = $detected['healthcheck.checker.messenger.transport.async'];
        self::assertSame(MessengerTransportChecker::class, $checker->getClass());
        self::assertEquals(new Reference('messenger.transport.async'), $checker->getArgument(0));
        self::assertSame('async', $checker->getArgument(1));
    }

    public function testDetectKeepsServiceIdWhenPrefixAbsent(): void
    {
        $container = new ContainerBuilder();
        $transport = new Definition(TransportInterface::class);
        $transport->addTag('messenger.receiver');

        $container->setDefinition('custom.queue', $transport);

        $detected = iterator_to_array(new MessengerTransportDetector()->detect($container));

        $checker = $detected['healthcheck.checker.custom.queue'];
        self::assertSame('custom.queue', $checker->getArgument(1));
    }

    public function testDetectIgnoresUntaggedTransports(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('messenger.transport.untagged', new Definition(TransportInterface::class));

        self::assertSame([], iterator_to_array(new MessengerTransportDetector()->detect($container)));
    }
}
