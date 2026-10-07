<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\RedisClusterChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use RedisCluster;
use RedisClusterException;

#[RequiresPhpExtension('redis')]
final class RedisClusterCheckerTest extends TestCase
{
    public function testIsSupportReadinessOnly(): void
    {
        $checker = new RedisClusterChecker(self::createStub(RedisCluster::class));

        self::assertTrue($checker->isSupport(new Context(CheckTypeEnum::READINESS)));
        self::assertFalse($checker->isSupport(new Context(CheckTypeEnum::LIVELINESS)));
    }

    public function testCheckWritesTheProbeKeyWithOneSecondTtl(): void
    {
        $cluster = $this->createMock(RedisCluster::class);
        $cluster->expects(self::once())
            ->method('set')
            ->with(CheckInterface::PROBE_KEY, self::callback(static fn (mixed $value): bool => is_string($value) && ctype_digit($value)), ['EX' => 1])
            ->willReturn(true)
        ;

        $result = new RedisClusterChecker($cluster)->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Redis cluster connection passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCheckFailsWhenSetReturnsFalse(): void
    {
        $cluster = self::createStub(RedisCluster::class);
        $cluster->method('set')->willReturn(false);

        $result = new RedisClusterChecker($cluster)->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Redis cluster connection failed (SET command returned false)'], $result->errors);
        self::assertSame([], $result->messages);
    }

    public function testCheckFailsOnClusterException(): void
    {
        $cluster = self::createStub(RedisCluster::class);
        $cluster->method('set')->willThrowException(new RedisClusterException("Couldn't map cluster keyspace using any provided seed"));

        $result = new RedisClusterChecker($cluster)->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(["Redis cluster connection failed (Couldn't map cluster keyspace using any provided seed)"], $result->errors);
        self::assertSame([], $result->messages);
    }
}
