<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use Doctrine\DBAL\Connection;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\DBALConnectionChecker;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DBALConnectionCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Connection::class)) {
            self::markTestSkipped('doctrine/dbal is not installed');
        }
    }

    public function testIsSupportReadinessOnly(): void
    {
        $checker = new DBALConnectionChecker(self::createStub(Connection::class), 'default');

        self::assertTrue($checker->isSupport(new Context(CheckTypeEnum::READINESS)));
        self::assertFalse($checker->isSupport(new Context(CheckTypeEnum::LIVELINESS)));
    }

    public function testCheckOnAlreadyConnected(): void
    {
        $connection = self::createStub(Connection::class);
        $connection->method('isConnected')->willReturn(true);

        $result = new DBALConnectionChecker($connection, 'default')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['DB connection (default) passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCheckRequestsServerVersionWhenDisconnected(): void
    {
        $connection = self::createStub(Connection::class);
        $connection->method('isConnected')->willReturn(false);
        $connection->method('getServerVersion')->willReturn('15.4');

        $result = new DBALConnectionChecker($connection, 'default')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['DB connection (default) passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCheckOnException(): void
    {
        $connection = self::createStub(Connection::class);
        $connection->method('isConnected')->willThrowException(new RuntimeException('boom'));

        $result = new DBALConnectionChecker($connection, 'default')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertCount(1, $result->errors);
        self::assertStringContainsString('default', $result->errors[0]);
        self::assertStringContainsString('boom', $result->errors[0]);
    }
}
