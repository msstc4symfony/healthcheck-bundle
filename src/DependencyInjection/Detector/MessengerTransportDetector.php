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
            // Symfony registers transports as "messenger.transport.X"; strip the prefix for a clean name.
            // (preg_replace on a literal pattern can only return string|null on regex errors, which
            // is impossible here — no defensive fallback needed.)
            $name = (string) preg_replace('/^messenger\.transport\./', '', (string) $id);

            yield sprintf('healthcheck.checker.%s', $id) => new Definition(MessengerTransportChecker::class)
                ->addArgument(new Reference($id))
                ->addArgument($name)
            ;
        }
    }
}
