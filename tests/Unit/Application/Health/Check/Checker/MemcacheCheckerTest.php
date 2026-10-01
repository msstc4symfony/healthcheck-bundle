<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use Memcache;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\MemcacheChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[RequiresPhpExtension('memcache')]
final class MemcacheCheckerTest extends TestCase
{
    public function testIsSupportReadinessOnly(): void
    {
        $checker = new MemcacheChecker(self::createStub(Memcache::class));

        self::assertTrue($checker->isSupport(new Context(CheckTypeEnum::READINESS)));
        self::assertFalse($checker->isSupport(new Context(CheckTypeEnum::LIVELINESS)));
    }

    public function testCheckOnSuccess(): void
    {
        $client = self::createStub(Memcache::class);
        $client->method('set')->willReturn(true);

        $result = new MemcacheChecker($client)->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Memcache connection passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCheckOnFailure(): void
    {
        $client = self::createStub(Memcache::class);
        $client->method('set')->willReturn(false);

        $result = new MemcacheChecker($client)->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Memcache connection failed (SET command returned false)'], $result->errors);
        self::assertSame([], $result->messages);
    }

    public function testCheckOnException(): void
    {
        $client = self::createStub(Memcache::class);
        $client->method('set')->willThrowException(new RuntimeException('boom'));

        $result = new MemcacheChecker($client)->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertCount(1, $result->errors);
        self::assertStringContainsString('boom', $result->errors[0]);
    }
}
