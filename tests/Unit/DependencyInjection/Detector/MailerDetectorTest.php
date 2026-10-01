<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\DependencyInjection\Detector;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\MailerChecker;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\MailerDetector;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;

final class MailerDetectorTest extends TestCase
{
    public function testDetectYieldsCheckerForSmtpTransport(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('mailer.smtp', new Definition(SmtpTransport::class));

        $detected = iterator_to_array(new MailerDetector()->detect($container));

        $checker = $detected['healthcheck.checker.mailer.smtp'];
        self::assertSame(MailerChecker::class, $checker->getClass());
        self::assertEquals(new Reference('mailer.smtp'), $checker->getArgument(0));
        self::assertSame('mailer.smtp', $checker->getArgument(1));
    }

    public function testDetectMatchesSmtpTransportSubclass(): void
    {
        // EsmtpTransport extends SmtpTransport in symfony/mailer.
        $container = new ContainerBuilder();
        $container->setDefinition('mailer.esmtp', new Definition(EsmtpTransport::class));

        $detected = iterator_to_array(new MailerDetector()->detect($container));

        self::assertArrayHasKey('healthcheck.checker.mailer.esmtp', $detected);
    }

    public function testDetectIgnoresUnrelatedAndClasslessDefinitions(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.other', new Definition(stdClass::class));
        $container->setDefinition('app.classless', new Definition());

        self::assertSame([], iterator_to_array(new MailerDetector()->detect($container)));
    }
}
