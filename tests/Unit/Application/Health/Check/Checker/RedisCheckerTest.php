<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\RedisChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PHPUnit\Framework\TestCase;
use Redis;

final class RedisCheckerTest extends TestCase
{
    public function testIsSupportReadinessOnly(): void
    {
        $checker = new RedisChecker(self::createStub(Redis::class));

        self::assertTrue($checker->isSupport(new Context(CheckTypeEnum::READINESS)));
        self::assertFalse($checker->isSupport(new Context(CheckTypeEnum::LIVELINESS)));
    }

    public function testCheckOnSuccess(): void
    {
        $redis = self::createStub(Redis::class);
        $redis->method('set')->willReturn(true);

        $result = new RedisChecker($redis)->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Redis connection passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCheckOnFailure(): void
    {
        $redis = self::createStub(Redis::class);
        $redis->method('set')->willReturn(false);

        $result = new RedisChecker($redis)->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Redis connection failed (SET command returned false)'], $result->errors);
        self::assertSame([], $result->messages);
    }
}
