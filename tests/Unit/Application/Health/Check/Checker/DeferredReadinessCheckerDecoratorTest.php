<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\DeferredReadinessCheckerDecorator;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\SuccessChecker;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DeferredReadinessCheckerDecoratorTest extends TestCase
{
    public function testSupportsReadinessOnlyWithoutBuildingTheChecker(): void
    {
        $built = false;
        $decorator = new DeferredReadinessCheckerDecorator(static function () use (&$built): CheckInterface {
            $built = true;

            return new SuccessChecker();
        }, 'healthcheck.checker.store');

        self::assertTrue($decorator->isSupport(new Context(CheckTypeEnum::READINESS)));
        self::assertFalse($decorator->isSupport(new Context(CheckTypeEnum::LIVELINESS)));
        self::assertFalse($built);
    }

    public function testDelegatesToTheBuiltChecker(): void
    {
        $decorator = new DeferredReadinessCheckerDecorator(static fn (): CheckInterface => new SuccessChecker(), 'healthcheck.checker.store');

        $result = $decorator->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['success dump check'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testReportsConstructionFailureAsThisCheckFailing(): void
    {
        $decorator = new DeferredReadinessCheckerDecorator(static fn (): CheckInterface => throw new RuntimeException('Semaphore extension (sysvsem) is required.'), 'healthcheck.checker..lock.semaphore.store');

        $result = $decorator->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['healthcheck.checker..lock.semaphore.store failed (Semaphore extension (sysvsem) is required.)'], $result->errors);
        self::assertSame([], $result->messages);
    }
}
