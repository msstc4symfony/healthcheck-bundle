<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use Memcached;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\MemcachedChecker;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[RequiresPhpExtension('memcached')]
final class MemcachedCheckerTest extends TestCase
{
    public function testIsSupportReadinessOnly(): void
    {
        $checker = new MemcachedChecker(self::createStub(Memcached::class));

        self::assertTrue($checker->isSupport(new Context(CheckTypeEnum::READINESS)));
        self::assertFalse($checker->isSupport(new Context(CheckTypeEnum::LIVELINESS)));
    }

    public function testCheckOnSuccess(): void
    {
        $client = self::createStub(Memcached::class);
        $client->method('set')->willReturn(true);

        $result = new MemcachedChecker($client)->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Memcached connection passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCheckOnFailure(): void
    {
        $client = self::createStub(Memcached::class);
        $client->method('set')->willReturn(false);

        $result = new MemcachedChecker($client)->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Memcached connection failed (SET command returned false)'], $result->errors);
        self::assertSame([], $result->messages);
    }

    public function testCheckOnException(): void
    {
        $client = self::createStub(Memcached::class);
        $client->method('set')->willThrowException(new RuntimeException('boom'));

        $result = new MemcachedChecker($client)->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertCount(1, $result->errors);
        self::assertStringContainsString('boom', $result->errors[0]);
    }
}
