<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Infrastructure\Checker;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Msstc4Symfony\HealthCheckBundle\Infrastructure\Checker\RedisChecker;
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

        $result = (new RedisChecker($redis))->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Redis connection passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testWritesTheProbeKeyWithAOneSecondExpiry(): void
    {
        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('set')
            ->with(CheckInterface::PROBE_KEY, self::matchesRegularExpression('/^\d+$/'), ['ex' => 1])
            ->willReturn(true)
        ;

        (new RedisChecker($redis))->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));
    }

    public function testCheckOnFailure(): void
    {
        $redis = self::createStub(Redis::class);
        $redis->method('set')->willReturn(false);

        $result = (new RedisChecker($redis))->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Redis connection failed (SET command returned false)'], $result->errors);
        self::assertSame([], $result->messages);
    }
}
