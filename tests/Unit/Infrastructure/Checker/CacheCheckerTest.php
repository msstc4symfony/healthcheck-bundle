<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Infrastructure\Checker;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Msstc4Symfony\HealthCheckBundle\Infrastructure\Checker\CacheChecker;
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
        $checker = new CacheChecker(new NullAdapter(), 'cache.app', null);

        self::assertTrue($checker->isSupport(new Context(CheckTypeEnum::READINESS)));
        self::assertFalse($checker->isSupport(new Context(CheckTypeEnum::LIVELINESS)));
    }

    public function testCheckWithNullAdapterReportsSkipped(): void
    {
        $checker = new CacheChecker(new NullAdapter(), 'cache.app', null);

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

        $checker = new CacheChecker($adapter, 'cache.app', null);

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

        $checker = new CacheChecker($adapter, 'cache.app', null);

        $result = $checker->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame([], $result->messages);
        self::assertCount(1, $result->errors);
        self::assertStringContainsString('failed', $result->errors[0]);
    }

    public function testCheckWithThrowingAdapterReportsError(): void
    {
        $adapter = self::createStub(AdapterInterface::class);
        $adapter->method('getItem')->willThrowException(new RuntimeException('connection refused'));

        $checker = new CacheChecker($adapter, 'cache.app', null);

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

    public function testPoolsSharingAKeyProbeOnceWithinOneRun(): void
    {
        $saves = 0;
        $adapter = $this->countingAdapter(true, $saves);
        $context = new Context(CheckTypeEnum::READINESS);

        $first = (new CacheChecker($adapter, 'cache.app', null, 'redis:c'))->check(new CheckResult(), $context);
        $second = (new CacheChecker($adapter, 'metrics.cache', null, 'redis:c'))->check(new CheckResult(), $context);

        self::assertSame(1, $saves);
        self::assertSame(['Cache (cache.app) connection passed'], $first->messages);
        self::assertSame(['Cache (metrics.cache) connection passed (shared connection probed via Cache (cache.app) connection)'], $second->messages);
        self::assertSame([], $second->errors);
    }

    public function testAFailedSharedProbeNamesThePoolThatWasProbed(): void
    {
        $saves = 0;
        $adapter = $this->countingAdapter(false, $saves);
        $context = new Context(CheckTypeEnum::READINESS);

        $first = (new CacheChecker($adapter, 'cache.app', null, 'redis:c'))->check(new CheckResult(), $context);
        $second = (new CacheChecker($adapter, 'metrics.cache', null, 'redis:c'))->check(new CheckResult(), $context);

        self::assertSame(1, $saves);
        self::assertSame(['Cache (cache.app) connection failed (save() returned false)'], $first->errors);
        self::assertSame(['Cache (metrics.cache) connection: shared connection probe via Cache (cache.app) connection failed: save() returned false'], $second->errors);
    }

    public function testPoolsWithDifferentOrNoKeysAreProbedEach(): void
    {
        $saves = 0;
        $adapter = $this->countingAdapter(true, $saves);
        $context = new Context(CheckTypeEnum::READINESS);

        foreach (['redis:c', 'redis_tag_aware:c', null, null] as $position => $key) {
            (new CacheChecker($adapter, 'pool' . $position, null, $key))->check(new CheckResult(), $context);
        }

        self::assertSame(4, $saves);
    }

    public function testEveryRunProbesAgain(): void
    {
        $saves = 0;
        $adapter = $this->countingAdapter(true, $saves);

        for ($run = 0; $run < 2; $run++) {
            (new CacheChecker($adapter, 'cache.app', null, 'redis:c'))->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));
        }

        self::assertSame(2, $saves);
    }

    private function countingAdapter(bool $saved, int &$saves): AdapterInterface
    {
        $adapter = self::createStub(AdapterInterface::class);
        $adapter->method('getItem')->willReturn(new CacheItem());
        $adapter->method('save')->willReturnCallback(static function () use (&$saves, $saved): bool {
            $saves++;

            return $saved;
        });

        return $adapter;
    }
}
