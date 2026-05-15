<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\MessengerTransportChecker;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class MessengerTransportCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(TransportInterface::class)) {
            self::markTestSkipped('symfony/messenger is not installed');
        }
    }

    public function testIsSupportReadinessOnly(): void
    {
        $checker = new MessengerTransportChecker(self::createStub(TransportInterface::class), 'async');

        self::assertTrue($checker->isSupport(new Context(CheckTypeEnum::READINESS)));
        self::assertFalse($checker->isSupport(new Context(CheckTypeEnum::LIVELINESS)));
    }

    public function testCheckUsesMessageCountWhenAvailable(): void
    {
        $transport = self::createStub(CountableMessengerTransportFixture::class);
        $transport->method('getMessageCount')->willReturn(7);

        $result = new MessengerTransportChecker($transport, 'async')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Messenger transport (async) passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCheckEmitsSkippedMessageForNonCountableTransport(): void
    {
        // For transports without MessageCountAware, no active probe runs (we refuse to call
        // destructive setup()). The checker reports "skipped" — NOT "passed" — so operators
        // see a truthful picture.
        $transport = self::createStub(TransportInterface::class);

        $result = new MessengerTransportChecker($transport, 'async')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame([], $result->errors);
        self::assertCount(1, $result->messages);
        self::assertStringContainsString('skipped', $result->messages[0]);
        self::assertStringContainsString('async', $result->messages[0]);
    }

    public function testCheckOnException(): void
    {
        $transport = self::createStub(CountableMessengerTransportFixture::class);
        $transport->method('getMessageCount')->willThrowException(new RuntimeException('broker offline'));

        $result = new MessengerTransportChecker($transport, 'async')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertCount(1, $result->errors);
        self::assertStringContainsString('async', $result->errors[0]);
        self::assertStringContainsString('broker offline', $result->errors[0]);
    }
}

interface CountableMessengerTransportFixture extends TransportInterface, MessageCountAwareInterface
{
}
