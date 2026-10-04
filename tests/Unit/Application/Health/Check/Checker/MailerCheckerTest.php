<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\MailerChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;

final class MailerCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(SmtpTransport::class)) {
            self::markTestSkipped('symfony/mailer is not installed');
        }
    }

    public function testIsSupportReadinessOnly(): void
    {
        $checker = new MailerChecker(self::createStub(SmtpTransport::class), 'smtp');

        self::assertTrue($checker->isSupport(new Context(CheckTypeEnum::READINESS)));
        self::assertFalse($checker->isSupport(new Context(CheckTypeEnum::LIVELINESS)));
    }

    public function testCheckOnSuccess(): void
    {
        $transport = self::createStub(SmtpTransport::class);

        $result = new MailerChecker($transport, 'smtp')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Mailer SMTP (smtp) passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCheckOnExceptionEmitsSanitizedMessage(): void
    {
        $transport = self::createStub(SmtpTransport::class);
        // The DSN-fragment "smtp://user:s3cret@host" must NEVER appear in the response;
        // the checker is required to swallow the underlying exception and emit a generic message.
        $transport->method('start')->willThrowException(new RuntimeException('smtp://user:s3cret@host: connect timeout'));

        $result = new MailerChecker($transport, 'smtp')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertCount(1, $result->errors);
        self::assertStringContainsString('smtp', $result->errors[0]);
        self::assertStringContainsString('SMTP connect/authentication failed', $result->errors[0]);
        self::assertStringNotContainsString('s3cret', $result->errors[0]);
        self::assertStringNotContainsString('user:', $result->errors[0]);
    }
}
