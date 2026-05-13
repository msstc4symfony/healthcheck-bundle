<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\DependencyInjection\Detector;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\MessengerTransportChecker;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final readonly class MessengerTransportDetector implements CheckerDetectorInterface
{
    /**
     * @return iterable<string, Definition>
     */
    public function detect(ContainerBuilder $container): iterable
    {
        foreach (array_keys($container->findTaggedServiceIds('messenger.receiver')) as $id) {
            // Symfony sets up "messenger.transport.X" service ids; the tag is on the receiver alias.
            $name = preg_replace('/^messenger\.transport\./', '', (string) $id) ?? (string) $id;
            yield sprintf('healthcheck.checker.%s', $id) => new Definition(MessengerTransportChecker::class)
                ->addArgument(new Reference($id))
                ->addArgument($name)
            ;
        }
    }
}
