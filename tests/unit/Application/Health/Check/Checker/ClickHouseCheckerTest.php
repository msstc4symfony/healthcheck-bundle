<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use ClickHouseDB\Client;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\ClickHouseChecker;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ClickHouseCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Client::class)) {
            self::markTestSkipped('smi2/phpclickhouse is not installed');
        }
    }

    public function testIsSupportReadinessOnly(): void
    {
        $checker = new ClickHouseChecker(self::createStub(Client::class), 'analytics');

        self::assertTrue($checker->isSupport(new Context(CheckTypeEnum::READINESS)));
        self::assertFalse($checker->isSupport(new Context(CheckTypeEnum::LIVELINESS)));
    }

    public function testCheckOnSuccess(): void
    {
        $client = self::createStub(Client::class);
        $client->method('ping')->willReturn(true);

        $result = new ClickHouseChecker($client, 'analytics')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['ClickHouse (analytics) passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCheckOnPingFalse(): void
    {
        $client = self::createStub(Client::class);
        $client->method('ping')->willReturn(false);

        $result = new ClickHouseChecker($client, 'analytics')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['ClickHouse (analytics) failed (ping returned false)'], $result->errors);
    }

    public function testCheckOnException(): void
    {
        $client = self::createStub(Client::class);
        $client->method('ping')->willThrowException(new RuntimeException('connection refused'));

        $result = new ClickHouseChecker($client, 'analytics')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertCount(1, $result->errors);
        self::assertStringContainsString('connection refused', $result->errors[0]);
    }
}
