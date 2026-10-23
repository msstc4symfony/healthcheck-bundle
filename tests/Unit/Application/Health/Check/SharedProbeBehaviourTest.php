<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Application\Health\Check;

use Closure;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Action;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\DeferredReadinessCheckerDecorator;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\NonCriticalCheckerDecorator;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\TimeoutCheckerDecorator;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Request;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Response;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Event\SafeEventDispatcher;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\SharedKeyCheckerFixture;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Pools sharing a probe key, each wrapped like the container wraps them, run by the real Action.
 */
final class SharedProbeBehaviourTest extends TestCase
{
    public function testALaterPoolWithASmallerBudgetFailsOnTheSlowSharedProbe(): void
    {
        $response = $this->execute(null, [
            $this->timed($this->pool('a', 60), 1000),
            $this->timed($this->pool('b', 60), 20),
        ]);

        self::assertSame(['Pool (a) passed'], $response->messages);
        self::assertCount(1, $response->errors);
        self::assertMatchesRegularExpression('/^Pool \(b\) exceeded budget \(\d+ ms > 20 ms\)$/', $response->errors[0]);
    }

    public function testASlowNonCriticalFirstPoolStillFailsALaterCriticalOne(): void
    {
        $response = $this->execute(null, [
            new NonCriticalCheckerDecorator($this->timed($this->pool('a', 60), 20)),
            $this->timed($this->pool('b', 60), 20),
        ]);

        self::assertCount(1, $response->warnings);
        self::assertStringStartsWith('Pool (a) exceeded budget (', $response->warnings[0]);
        self::assertCount(1, $response->errors);
        self::assertStringStartsWith('Pool (b) exceeded budget (', $response->errors[0]);
        self::assertFalse($response->success);
    }

    public function testAFailingNonCriticalFirstPoolStillFailsALaterCriticalOne(): void
    {
        $response = $this->execute(null, [
            new NonCriticalCheckerDecorator($this->timed($this->pool('a', 0, true), 1000)),
            $this->timed($this->pool('b', 0, true), 1000),
        ]);

        self::assertSame(['Pool (a) failed (refused)'], $response->warnings);
        self::assertSame(['Pool (b): shared connection probe via Pool (a) failed: refused'], $response->errors);
    }

    public function testPoolsOnOtherKeysAreProbedEach(): void
    {
        $response = $this->execute(null, [
            $this->timed($this->pool('a', 0, true), 1000),
            $this->timed(new SharedKeyCheckerFixture(static fn (): null => throw new RuntimeException('refused'), 'b', 'redis:other'), 1000),
        ]);

        self::assertSame(['Pool (a) failed (refused)', 'Pool (b) failed (refused)'], $response->errors);
    }

    public function testASkippedFirstPoolLeavesTheLaterOnesSkippedNotPassed(): void
    {
        $response = $this->execute(1, [
            $this->timed(new SharedKeyCheckerFixture(static function (): null {
                usleep(30_000);

                return null;
            }, 'first', null), 1000),
            $this->timed($this->pool('a', 0), 1000),
            $this->timed($this->pool('b', 0), 1000),
        ]);

        self::assertSame(['Pool (first) passed'], $response->messages);
        self::assertCount(2, $response->errors);
        self::assertStringStartsWith('Pool (a) skipped: probe budget exhausted (', $response->errors[0]);
        self::assertStringStartsWith('Pool (b) skipped: probe budget exhausted (', $response->errors[1]);
    }

    public function testDeferredPoolsBuildTheTargetOnceAndShareAConstructionFailure(): void
    {
        $built = 0;
        $factory = static function () use (&$built): CheckInterface {
            $built++;
            usleep(40_000);

            throw new RuntimeException('connect refused');
        };

        $response = $this->execute(null, [
            new DeferredReadinessCheckerDecorator($factory, 'Pool (a)', SharedKeyCheckerFixture::class, 'redis:c', 1000),
            new DeferredReadinessCheckerDecorator($factory, 'Pool (b)', SharedKeyCheckerFixture::class, 'redis:c', 1000),
        ]);

        self::assertSame(1, $built);
        self::assertSame(
            ['Pool (a) failed (connect refused)', 'Pool (b): shared connection probe via Pool (a) failed: connect refused'],
            $response->errors,
        );
    }

    public function testADeferredPoolReusesASharedPassAndChecksItAgainstItsOwnBudget(): void
    {
        $built = 0;
        $factory = function () use (&$built): CheckInterface {
            $built++;

            return $this->timed($this->pool('a', 60), 1000);
        };

        $response = $this->execute(null, [
            new DeferredReadinessCheckerDecorator($factory, 'Pool (a)', SharedKeyCheckerFixture::class, 'redis:c', 1000),
            new DeferredReadinessCheckerDecorator($factory, 'Pool (b)', SharedKeyCheckerFixture::class, 'redis:c', 20),
            new DeferredReadinessCheckerDecorator($factory, 'Pool (c)', SharedKeyCheckerFixture::class, 'redis:c', 1000),
        ]);

        self::assertSame(1, $built);
        self::assertSame(['Pool (a) passed', 'Pool (c) passed (shared connection probed via Pool (a))'], $response->messages);
        self::assertCount(1, $response->errors);
        self::assertMatchesRegularExpression('/^Pool \(b\) exceeded budget \(\d+ ms > 20 ms\)$/', $response->errors[0]);
    }

    public function testASlowSharedFailureExceedsASmallerBudgetTheSameWayInBothPaths(): void
    {
        $plain = $this->execute(null, [
            $this->timed($this->pool('a', 60, true), 1000),
            $this->timed($this->pool('b', 60, true), 20),
        ]);
        $factory = fn (): CheckInterface => $this->timed($this->pool('a', 60, true), 1000);
        $deferred = $this->execute(null, [
            $this->deferred($factory, 'Pool (a)', 1000),
            $this->deferred($factory, 'Pool (b)', 20),
        ]);

        foreach ([$plain, $deferred] as $response) {
            self::assertCount(2, $response->errors);
            self::assertSame('Pool (a) failed (refused)', $response->errors[0]);
            self::assertMatchesRegularExpression('/^Pool \(b\) exceeded budget \(\d+ ms > 20 ms\)$/', $response->errors[1]);
        }
    }

    public function testAnUnkeyedDeferredPoolAlwaysBuilds(): void
    {
        $built = 0;
        $factory = static function () use (&$built): CheckInterface {
            $built++;

            throw new RuntimeException('no');
        };

        $this->execute(null, [
            new DeferredReadinessCheckerDecorator($factory, 'Pool (a)', SharedKeyCheckerFixture::class),
            new DeferredReadinessCheckerDecorator($factory, 'Pool (b)', SharedKeyCheckerFixture::class),
        ]);

        self::assertSame(2, $built);
    }

    /**
     * @param list<CheckInterface> $checkers
     * @param int<1, max>|null $budgetMs
     */
    private function execute(?int $budgetMs, array $checkers): Response
    {
        return (new Action($checkers, new SafeEventDispatcher(), $budgetMs))->run(new Request(CheckTypeEnum::READINESS));
    }

    /**
     * @param non-negative-int $sleepMs
     */
    private function pool(string $name, int $sleepMs, bool $fails = false): SharedKeyCheckerFixture
    {
        $probe = static function () use ($sleepMs, $fails): null {
            usleep($sleepMs * 1000);
            if ($fails) {
                throw new RuntimeException('refused');
            }

            return null;
        };

        return new SharedKeyCheckerFixture(Closure::fromCallable($probe), $name);
    }

    /**
     * @param Closure(): CheckInterface $factory
     * @param non-empty-string $label
     * @param positive-int $timeoutMs
     */
    private function deferred(Closure $factory, string $label, int $timeoutMs): CheckInterface
    {
        return new DeferredReadinessCheckerDecorator($factory, $label, SharedKeyCheckerFixture::class, 'redis:c', $timeoutMs);
    }

    /**
     * @param positive-int $timeoutMs
     */
    private function timed(CheckInterface $checker, int $timeoutMs): CheckInterface
    {
        return new TimeoutCheckerDecorator($checker, $timeoutMs);
    }
}
