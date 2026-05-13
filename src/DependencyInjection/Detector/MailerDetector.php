<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\DependencyInjection\Detector;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\MailerChecker;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;

final readonly class MailerDetector implements CheckerDetectorInterface
{
    /**
     * @return iterable<string, Definition>
     */
    public function detect(ContainerBuilder $container): iterable
    {
        // Auto-detects services explicitly declared as SmtpTransport (or subclass).
        // Symfony's standard mailer wiring does NOT register concrete transports as named services;
        // users who want SMTP probing register an SmtpTransport service themselves.
        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();
            if ($class === null || ($class !== SmtpTransport::class && !is_subclass_of($class, SmtpTransport::class))) {
                continue;
            }

            yield sprintf('healthcheck.checker.%s', $id) => new Definition(MailerChecker::class)
                ->addArgument(new Reference($id))
                ->addArgument($id)
            ;
        }
    }
}
