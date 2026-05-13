<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;

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
        // Opens TCP socket, sends EHLO, no actual mail dispatched.
        $this->transport->start();
        $this->transport->stop();
    }

    protected function label(): string
    {
        return sprintf('Mailer SMTP (%s)', $this->name);
    }
}
