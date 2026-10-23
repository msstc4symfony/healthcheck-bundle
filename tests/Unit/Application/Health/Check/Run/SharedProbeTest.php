<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Application\Health\Check\Run;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Run\SharedProbe;
use PHPUnit\Framework\TestCase;

final class SharedProbeTest extends TestCase
{
    public function testReportsAPassWithTheProbedLabel(): void
    {
        $result = new CheckResult();

        (new SharedProbe('Cache (a)', null, 10))->reportTo($result, 'Cache (b)', 2000);

        self::assertSame(['Cache (b) passed (shared connection probed via Cache (a))'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testReportsAFailureNamingTheProbedChecker(): void
    {
        $result = new CheckResult();

        (new SharedProbe('Cache (a)', 'refused', 10))->reportTo($result, 'Cache (b)', 2000);

        self::assertSame(['Cache (b): shared connection probe via Cache (a) failed: refused'], $result->errors);
        self::assertSame([], $result->messages);
    }

    public function testAPassSlowerThanTheReusingBudgetExceedsIt(): void
    {
        $result = new CheckResult();

        (new SharedProbe('Cache (a)', null, 3000))->reportTo($result, 'Cache (b)', 2000);

        self::assertSame(['Cache (b) exceeded budget (3000 ms > 2000 ms)'], $result->errors);
        self::assertSame([], $result->messages);
    }

    public function testAFailureSlowerThanTheReusingBudgetExceedsItLikeTheTimeoutDecorator(): void
    {
        $result = new CheckResult();

        (new SharedProbe('Cache (a)', 'refused', 3000))->reportTo($result, 'Cache (b)', 2000);

        self::assertSame(['Cache (b) exceeded budget (3000 ms > 2000 ms)'], $result->errors);
        self::assertSame([], $result->messages);
    }

    public function testAPassExactlyAtTheBudgetStillPasses(): void
    {
        $result = new CheckResult();

        (new SharedProbe('Cache (a)', null, 2000))->reportTo($result, 'Cache (b)', 2000);

        self::assertSame([], $result->errors);
    }

    public function testWithoutABudgetAPassIsNeverTooSlow(): void
    {
        $result = new CheckResult();

        (new SharedProbe('Cache (a)', null, 99_999))->reportTo($result, 'Cache (b)', null);

        self::assertSame([], $result->errors);
    }

    public function testAFailureExactlyAtTheBudgetIsReportedAsTheFailure(): void
    {
        $result = new CheckResult();

        (new SharedProbe('Cache (a)', 'refused', 2000))->reportTo($result, 'Cache (b)', 2000);

        self::assertSame(['Cache (b): shared connection probe via Cache (a) failed: refused'], $result->errors);
    }

    public function testWithoutABudgetASlowFailureIsReportedAsTheFailure(): void
    {
        $result = new CheckResult();

        (new SharedProbe('Cache (a)', 'refused', 99_999))->reportTo($result, 'Cache (b)', null);

        self::assertSame(['Cache (b): shared connection probe via Cache (a) failed: refused'], $result->errors);
    }

    public function testFactoriesTruncateTheElapsedTimeToWholeMilliseconds(): void
    {
        self::assertSame(2999, SharedProbe::passed('x', 1000, 2_999_999_999 + 1000)->durationMs);
        self::assertSame(2999, SharedProbe::failed('x', 'boom', 1000, 2_999_999_999 + 1000)->durationMs);
        self::assertSame(3000, SharedProbe::passed('x', 0, 3_000_000_500)->durationMs);
        self::assertSame(0, SharedProbe::passed('x', 50_000_000, 4_000)->durationMs);
    }

    public function testFactoriesMeasureFromTheStartAndNormalizeEmptyText(): void
    {
        $passed = SharedProbe::passed('', 1_000, 50_001_000);
        $failed = SharedProbe::failed('Cache (a)', '', 1_000, 1_000);

        self::assertSame('checker', $passed->checkerLabel);
        self::assertNull($passed->error);
        self::assertSame(50, $passed->durationMs);
        self::assertSame('no message', $failed->error);
        self::assertSame(0, SharedProbe::passed('x', 10_000, 5_000)->durationMs);
    }
}
