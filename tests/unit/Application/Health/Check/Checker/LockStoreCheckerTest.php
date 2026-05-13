<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\LockStoreChecker;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Lock\PersistingStoreInterface;

final class LockStoreCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(PersistingStoreInterface::class)) {
            self::markTestSkipped('symfony/lock is not installed');
        }
    }

    public function testIsSupportReadinessOnly(): void
    {
        $checker = new LockStoreChecker(self::createStub(PersistingStoreInterface::class), 'default');

        self::assertTrue($checker->isSupport(new Context(CheckTypeEnum::READINESS)));
        self::assertFalse($checker->isSupport(new Context(CheckTypeEnum::LIVELINESS)));
    }

    public function testCheckOnSuccess(): void
    {
        $store = self::createStub(PersistingStoreInterface::class);

        $result = new LockStoreChecker($store, 'default')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Lock store (default) passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCheckOnException(): void
    {
        $store = self::createStub(PersistingStoreInterface::class);
        $store->method('save')->willThrowException(new RuntimeException('store unavailable'));

        $result = new LockStoreChecker($store, 'default')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertCount(1, $result->errors);
        self::assertStringContainsString('default', $result->errors[0]);
        self::assertStringContainsString('store unavailable', $result->errors[0]);
    }
}
