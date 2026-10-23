<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\DeferredReadinessCheckerDecorator;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Run\ProbeBudget;
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
        }, 'Store (main)', SuccessChecker::class);

        self::assertTrue($decorator->isSupport(new Context(CheckTypeEnum::READINESS)));
        self::assertFalse($decorator->isSupport(new Context(CheckTypeEnum::LIVELINESS)));
        self::assertFalse($built);
    }

    public function testDelegatesToTheBuiltChecker(): void
    {
        $decorator = new DeferredReadinessCheckerDecorator(static fn (): CheckInterface => new SuccessChecker(), 'Store (main)', SuccessChecker::class);

        $result = $decorator->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['success dump check'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testDoesNotBuildTheCheckerOnceTheProbeBudgetIsExhausted(): void
    {
        $built = false;
        $decorator = new DeferredReadinessCheckerDecorator(static function () use (&$built): CheckInterface {
            $built = true;

            return new SuccessChecker();
        }, 'Store (main)', SuccessChecker::class);

        $budget = new ProbeBudget(hrtime(true) - 10_000_000_000, 100);
        $budget->checkerFinished();

        $result = $decorator->check(new CheckResult(), new Context(CheckTypeEnum::READINESS, [], $budget));

        self::assertFalse($built);
        self::assertMatchesRegularExpression('/^Store \\(main\\) skipped: probe budget exhausted \\(\\d+ ms > 100 ms\\)$/', $result->errors[0]);
    }

    public function testCountsAConstructionFailureAsAFinishedChecker(): void
    {
        $budget = new ProbeBudget(hrtime(true) - 10_000_000_000, 1);
        $decorator = new DeferredReadinessCheckerDecorator(static fn (): CheckInterface => throw new RuntimeException('no'), 'Store (main)', SuccessChecker::class);

        $decorator->check(new CheckResult(), new Context(CheckTypeEnum::READINESS, [], $budget));

        self::assertNotNull($budget->exhaustedMessage('Next', hrtime(true)));
    }

    public function testReportsConstructionFailureAsThisCheckFailing(): void
    {
        $decorator = new DeferredReadinessCheckerDecorator(static fn (): CheckInterface => throw new RuntimeException('Semaphore extension (sysvsem) is required.'), 'Lock store (.lock.semaphore.store)', SuccessChecker::class);

        $result = $decorator->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Lock store (.lock.semaphore.store) failed (Semaphore extension (sysvsem) is required.)'], $result->errors);
        self::assertSame([], $result->messages);
    }

    public function testRedactsCredentialsInConstructionFailure(): void
    {
        $decorator = new DeferredReadinessCheckerDecorator(static fn (): CheckInterface => throw new RuntimeException('Invalid DSN "redis://admin:s3cret@redis:6379/0"'), 'Lock store (.lock.default.store.abc)', SuccessChecker::class);

        $result = $decorator->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Lock store (.lock.default.store.abc) failed (Invalid DSN "redis://***@redis:6379/0")'], $result->errors);
    }

    public function testExposesTheLabelWithoutBuildingTheChecker(): void
    {
        $decorator = new DeferredReadinessCheckerDecorator(static fn (): CheckInterface => throw new RuntimeException('must not be built'), 'Lock store (x)', SuccessChecker::class);

        self::assertSame('Lock store (x)', $decorator->checkerLabel());
    }

    public function testNamesTheDeferredCheckerWithoutBuildingIt(): void
    {
        $decorator = new DeferredReadinessCheckerDecorator(static fn (): CheckInterface => throw new RuntimeException('must not be built'), 'Lock store (x)', SuccessChecker::class);

        self::assertSame(SuccessChecker::class, $decorator->decoratedCheckerClass());
    }
}
