<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\PredisChecker;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use RuntimeException;

final class PredisCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Client::class)) {
            self::markTestSkipped('predis/predis is not installed');
        }
    }

    public function testIsSupportReadinessOnly(): void
    {
        $checker = new PredisChecker(self::createStub(Client::class));

        self::assertTrue($checker->isSupport(new Context(CheckTypeEnum::READINESS)));
        self::assertFalse($checker->isSupport(new Context(CheckTypeEnum::LIVELINESS)));
    }

    public function testCheckOnAlreadyConnected(): void
    {
        $client = self::createStub(Client::class);
        $client->method('isConnected')->willReturn(true);

        $result = new PredisChecker($client)->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Redis connection passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCheckConnectsThenSucceeds(): void
    {
        $client = self::createStub(Client::class);
        $client->method('isConnected')->willReturnOnConsecutiveCalls(false, true);

        $result = new PredisChecker($client)->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Redis connection passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCheckConnectsButStaysDisconnected(): void
    {
        $client = self::createStub(Client::class);
        $client->method('isConnected')->willReturn(false);

        $result = new PredisChecker($client)->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Redis connection failed (not connected after reconnect)'], $result->errors);
        self::assertSame([], $result->messages);
    }

    public function testCheckOnException(): void
    {
        $client = self::createStub(Client::class);
        $client->method('isConnected')->willThrowException(new RuntimeException('boom'));

        $result = new PredisChecker($client)->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame([], $result->messages);
        self::assertCount(1, $result->errors);
        self::assertStringContainsString('boom', $result->errors[0]);
    }
}
