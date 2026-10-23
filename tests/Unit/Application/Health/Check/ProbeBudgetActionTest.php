<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Application\Health\Check;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Action;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\ActionInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\NonCriticalCheckerDecorator;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\TimeoutCheckerDecorator;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Request;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Event\HealthCheckerCompletedEvent;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Event\HealthCheckRunCompletedEvent;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Event\SafeEventDispatcher;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\ParallelAction;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\SlowChecker;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Event\RecordingDispatcherFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProbeBudgetActionTest extends TestCase
{
    /**
     * @param 'sequential'|'parallel' $execution
     */
    #[DataProvider('runners')]
    public function testSkipsTheCheckersNotYetStartedOnceTheBudgetIsSpent(string $execution): void
    {
        $dispatcher = new RecordingDispatcherFixture();
        $action = $this->action($execution, [
            $this->timed(new SlowChecker(200, 'first')),
            $this->timed(new SlowChecker(0, 'second')),
            $this->timed(new SlowChecker(0, 'third')),
        ], new SafeEventDispatcher($dispatcher), 50);

        $response = $action->run(new Request(CheckTypeEnum::READINESS));

        self::assertFalse($response->success);
        self::assertSame(['Slow (first) passed'], $response->messages);
        self::assertCount(2, $response->errors);
        self::assertMatchesRegularExpression('/^Slow \(second\) skipped: probe budget exhausted \(\d+ ms > 50 ms\)$/', $response->errors[0]);
        self::assertMatchesRegularExpression('/^Slow \(third\) skipped: probe budget exhausted \(\d+ ms > 50 ms\)$/', $response->errors[1]);

        $completed = array_values(array_filter($dispatcher->events, static fn (object $event): bool => $event instanceof HealthCheckerCompletedEvent));
        self::assertCount(3, $completed);
        self::assertSame([true, false, false], array_map(static fn (HealthCheckerCompletedEvent $event): bool => $event->success, $completed));
        self::assertSame([false, false, false], array_map(static fn (HealthCheckerCompletedEvent $event): bool => $event->warned, $completed));
        self::assertInstanceOf(HealthCheckRunCompletedEvent::class, end($dispatcher->events));
    }

    /**
     * @param 'sequential'|'parallel' $execution
     */
    #[DataProvider('runners')]
    public function testSkippedNonCriticalCheckerIsAWarning(string $execution): void
    {
        $dispatcher = new RecordingDispatcherFixture();
        $action = $this->action($execution, [
            $this->timed(new SlowChecker(200, 'first')),
            new NonCriticalCheckerDecorator($this->timed(new SlowChecker(0, 'second'))),
        ], new SafeEventDispatcher($dispatcher), 50);

        $response = $action->run(new Request(CheckTypeEnum::READINESS));

        self::assertTrue($response->success);
        self::assertSame([], $response->errors);
        self::assertCount(1, $response->warnings);
        self::assertStringStartsWith('Slow (second) skipped: probe budget exhausted (', $response->warnings[0]);

        $completed = array_values(array_filter($dispatcher->events, static fn (object $event): bool => $event instanceof HealthCheckerCompletedEvent));
        self::assertTrue($completed[1]->success);
        self::assertTrue($completed[1]->warned);
    }

    /**
     * @param 'sequential'|'parallel' $execution
     */
    #[DataProvider('runners')]
    public function testTheFirstCheckerAlwaysRunsEvenWithATinyBudget(string $execution): void
    {
        $action = $this->action($execution, [
            $this->timed(new SlowChecker(30, 'first')),
            $this->timed(new SlowChecker(0, 'second')),
        ], new SafeEventDispatcher(), 1);

        $response = $action->run(new Request(CheckTypeEnum::READINESS));

        self::assertSame(['Slow (first) passed'], $response->messages);
        self::assertCount(1, $response->errors);
        self::assertStringStartsWith('Slow (second) skipped: probe budget exhausted (', $response->errors[0]);
    }

    /**
     * @param 'sequential'|'parallel' $execution
     */
    #[DataProvider('runners')]
    public function testRunsEveryCheckerWithoutABudget(string $execution): void
    {
        $action = $this->action($execution, [
            $this->timed(new SlowChecker(200, 'first')),
            $this->timed(new SlowChecker(0, 'second')),
        ], new SafeEventDispatcher(), null);

        $response = $action->run(new Request(CheckTypeEnum::READINESS));

        self::assertTrue($response->success);
        self::assertSame(['Slow (first) passed', 'Slow (second) passed'], $response->messages);
    }

    /**
     * @param 'sequential'|'parallel' $execution
     */
    #[DataProvider('runners')]
    public function testRunsEveryCheckerWhileTheBudgetHolds(string $execution): void
    {
        $action = $this->action($execution, [
            $this->timed(new SlowChecker(0, 'first')),
            $this->timed(new SlowChecker(0, 'second')),
        ], new SafeEventDispatcher(), 60_000);

        self::assertTrue($action->run(new Request(CheckTypeEnum::READINESS))->success);
    }

    /**
     * @return iterable<string, array{'sequential'|'parallel'}>
     */
    public static function runners(): iterable
    {
        yield 'sequential' => ['sequential'];
        yield 'parallel' => ['parallel'];
    }

    /**
     * @param 'sequential'|'parallel' $execution
     * @param list<CheckInterface> $checkers
     * @param int<1, max>|null $budgetMs
     */
    private function action(string $execution, array $checkers, SafeEventDispatcher $events, ?int $budgetMs): ActionInterface
    {
        return $execution === 'parallel' ? new ParallelAction($checkers, $events, $budgetMs) : new Action($checkers, $events, $budgetMs);
    }

    private function timed(CheckInterface $checker): CheckInterface
    {
        return new TimeoutCheckerDecorator($checker, 60_000);
    }
}
