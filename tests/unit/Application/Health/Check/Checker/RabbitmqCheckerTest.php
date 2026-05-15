<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\RabbitmqChecker;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PhpAmqpLib\Connection\AbstractConnection;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RabbitmqCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(AbstractConnection::class)) {
            self::markTestSkipped('php-amqplib/php-amqplib is not installed');
        }
    }

    public function testIsSupportReadinessOnly(): void
    {
        $checker = new RabbitmqChecker(self::createStub(AbstractConnection::class));

        self::assertTrue($checker->isSupport(new Context(CheckTypeEnum::READINESS)));
        self::assertFalse($checker->isSupport(new Context(CheckTypeEnum::LIVELINESS)));
    }

    public function testCheckOnSuccess(): void
    {
        $connection = self::createStub(AbstractConnection::class);
        $connection->method('isConnected')->willReturn(true);

        $result = new RabbitmqChecker($connection)->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['RabbitMQ connection passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCheckOnFailureWhenStillDisconnected(): void
    {
        $connection = self::createStub(AbstractConnection::class);
        $connection->method('isConnected')->willReturn(false);

        $result = new RabbitmqChecker($connection)->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['RabbitMQ connection failed (not connected after reconnect)'], $result->errors);
        self::assertSame([], $result->messages);
    }

    public function testCheckOnReconnectException(): void
    {
        $connection = self::createStub(AbstractConnection::class);
        $connection->method('reconnect')->willThrowException(new RuntimeException('boom'));

        $result = new RabbitmqChecker($connection)->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertCount(1, $result->errors);
        self::assertStringContainsString('boom', $result->errors[0]);
    }
}
