<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\DependencyInjection\Detector;

use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\MailerChecker;
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
        //
        // NOTE: Symfony's standard mailer wiring (>= 5.4) builds concrete transports lazily
        // inside Transports::__construct — they are NOT registered as named services. To enable
        // this detector, register an SmtpTransport service explicitly in your app config, e.g.:
        //
        //   App\Service\MyMailer\SmtpProbe:
        //       class: Symfony\Component\Mailer\Transport\Smtp\SmtpTransport
        //       arguments: ['$ssl://user:pass@smtp.example.com']
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
