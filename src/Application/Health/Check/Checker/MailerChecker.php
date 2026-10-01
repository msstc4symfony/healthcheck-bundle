<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

use Override;
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

    #[Override]
    protected function doCheck(): void
    {
        // Mailer transport exceptions may carry DSN fragments (incl. credentials) in their message.
        // Only the start() outcome reflects actual connectivity; a stop() failure is a best-effort
        // cleanup concern and must not be reported as a connect/auth failure.
        try {
            $this->transport->start();
        } catch (Throwable) {
            throw new RuntimeException('SMTP connect/authentication failed');
        }

        try {
            $this->transport->stop();
        } catch (Throwable) {
            // ignored — connection was already verified by start().
        }
    }

    #[Override]
    protected function label(): string
    {
        return sprintf('Mailer SMTP (%s)', $this->name);
    }
}
