<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker;

use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Throwable;

#[Exclude]
final readonly class MailerChecker extends AbstractReadinessChecker
{
    public function __construct(
        private SmtpTransport $transport,
        private string $name,
    ) {
    }

    protected function doCheck(): void
    {
        // Mailer transport exceptions may carry DSN fragments (incl. credentials) in their message.
        // Catch any throwable and re-throw a sanitized version so the probe response never leaks secrets.
        try {
            $this->transport->start();
            $this->transport->stop();
        } catch (Throwable) {
            throw new RuntimeException('SMTP connect/authentication failed');
        }
    }

    protected function label(): string
    {
        return sprintf('Mailer SMTP (%s)', $this->name);
    }
}
