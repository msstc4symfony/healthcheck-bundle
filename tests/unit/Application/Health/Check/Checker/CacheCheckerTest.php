<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\CacheChecker;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\CacheItem;

final class CacheCheckerTest extends TestCase
{
    public function testIsSupportReadinessOnly(): void
    {
        $checker = new CacheChecker(new NullAdapter(), 'cache.app');

        self::assertTrue($checker->isSupport(new Context(CheckTypeEnum::READINESS)));
        self::assertFalse($checker->isSupport(new Context(CheckTypeEnum::LIVELINESS)));
    }

    public function testCheckWithNullAdapterReportsSkipped(): void
    {
        $checker = new CacheChecker(new NullAdapter(), 'cache.app');

        $result = $checker->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame([], $result->errors);
        self::assertCount(1, $result->messages);
        self::assertStringContainsString('cache.app', $result->messages[0]);
        self::assertStringContainsString('skipped', $result->messages[0]);
        self::assertStringContainsString('NullAdapter', $result->messages[0]);
    }

    public function testCheckWithSuccessfulSaveReportsPassed(): void
    {
        $adapter = self::createStub(AdapterInterface::class);
        $adapter->method('getItem')->willReturn(new CacheItem());
        $adapter->method('save')->willReturn(true);

        $checker = new CacheChecker($adapter, 'cache.app');

        $result = $checker->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame([], $result->errors);
        self::assertCount(1, $result->messages);
        self::assertStringContainsString('passed', $result->messages[0]);
    }

    public function testCheckWithFailedSaveReportsError(): void
    {
        $adapter = self::createStub(AdapterInterface::class);
        $adapter->method('getItem')->willReturn(new CacheItem());
        $adapter->method('save')->willReturn(false);

        $checker = new CacheChecker($adapter, 'cache.app');

        $result = $checker->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame([], $result->messages);
        self::assertCount(1, $result->errors);
        self::assertStringContainsString('failed', $result->errors[0]);
    }

    public function testCheckWithThrowingAdapterReportsError(): void
    {
        $adapter = self::createStub(AdapterInterface::class);
        $adapter->method('getItem')->willThrowException(new RuntimeException('connection refused'));

        $checker = new CacheChecker($adapter, 'cache.app');

        $result = $checker->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame([], $result->messages);
        self::assertCount(1, $result->errors);
        self::assertStringContainsString('connection refused', $result->errors[0]);
        self::assertStringContainsString('cache.app', $result->errors[0]);
    }

    public function testCheckWithParentNameOnSuccess(): void
    {
        $adapter = self::createStub(AdapterInterface::class);
        $adapter->method('getItem')->willReturn(new CacheItem());
        $adapter->method('save')->willReturn(true);

        $checker = new CacheChecker($adapter, 'cache.app', RedisAdapter::class);

        $result = $checker->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame([], $result->errors);
        self::assertCount(1, $result->messages);
        self::assertStringContainsString(RedisAdapter::class, $result->messages[0]);
        self::assertStringContainsString('cache.app', $result->messages[0]);
    }

    public function testCheckWithParentNameOnSaveFailure(): void
    {
        $adapter = self::createStub(AdapterInterface::class);
        $adapter->method('getItem')->willReturn(new CacheItem());
        $adapter->method('save')->willReturn(false);

        $checker = new CacheChecker($adapter, 'cache.app', RedisAdapter::class);

        $result = $checker->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame([], $result->messages);
        self::assertCount(1, $result->errors);
        self::assertStringContainsString(RedisAdapter::class, $result->errors[0]);
    }

    public function testCheckWithParentNameOnException(): void
    {
        $adapter = self::createStub(AdapterInterface::class);
        $adapter->method('getItem')->willThrowException(new RuntimeException('boom'));

        $checker = new CacheChecker($adapter, 'cache.app', RedisAdapter::class);

        $result = $checker->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertCount(1, $result->errors);
        self::assertStringContainsString('boom', $result->errors[0]);
        self::assertStringContainsString(RedisAdapter::class, $result->errors[0]);
        self::assertStringContainsString('cache.app', $result->errors[0]);
    }
}
