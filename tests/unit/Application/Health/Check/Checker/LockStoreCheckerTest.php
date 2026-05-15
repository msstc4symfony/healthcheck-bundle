<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\LockStoreChecker;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
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

    public function testCheckOnSuccessReleasesLock(): void
    {
        $store = $this->createMock(PersistingStoreInterface::class);
        $store->expects(self::once())->method('save');
        $store->expects(self::once())->method('delete');

        $result = new LockStoreChecker($store, 'default')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Lock store (default) passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testDeleteFailureDoesNotMaskSuccess(): void
    {
        $store = $this->createMock(PersistingStoreInterface::class);
        $store->expects(self::once())->method('save');
        $store->expects(self::once())->method('delete')->willThrowException(new RuntimeException('cleanup boom'));

        $result = new LockStoreChecker($store, 'default')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        // delete failure is suppressed: the save succeeded → the checker should report passed.
        self::assertSame(['Lock store (default) passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testSaveFailureReported(): void
    {
        $store = self::createStub(PersistingStoreInterface::class);
        $store->method('save')->willThrowException(new RuntimeException('store unavailable'));

        $result = new LockStoreChecker($store, 'default')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertCount(1, $result->errors);
        self::assertStringContainsString('default', $result->errors[0]);
        self::assertStringContainsString('store unavailable', $result->errors[0]);
    }
}
