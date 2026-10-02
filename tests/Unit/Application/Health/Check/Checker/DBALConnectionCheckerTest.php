<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\DBALConnectionChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
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

    public function testCheckRunsDummySelectWhenDisconnected(): void
    {
        $platform = self::createStub(AbstractPlatform::class);
        $platform->method('getDummySelectSQL')->willReturn('SELECT 1');
        $connection = $this->createMock(Connection::class);
        $connection->method('isConnected')->willReturn(false);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $connection->expects(self::once())->method('executeQuery')->with('SELECT 1');

        $result = new DBALConnectionChecker($connection, 'default')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['DB connection (default) passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testCheckPassesAgainstARealDisconnectedConnection(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        $result = new DBALConnectionChecker($connection, 'default')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame([], $result->errors);
        self::assertSame(['DB connection (default) passed'], $result->messages);
        self::assertTrue($connection->isConnected());
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
