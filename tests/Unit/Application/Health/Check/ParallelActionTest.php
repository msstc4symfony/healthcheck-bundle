<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Application\Health\Check;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\NonCriticalCheckerDecorator;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\TimeoutCheckerDecorator;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Request;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Event\HealthCheckerCompletedEvent;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Event\HealthCheckRunCompletedEvent;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Event\HealthCheckRunStartedEvent;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Event\SafeEventDispatcher;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\ParallelAction;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\BrokenLabelChecker;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\FailChecker;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\LabeledThrowingChecker;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\SuccessChecker;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\ThrowingChecker;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Event\RecordingDispatcherFixture;
use Override;
use PHPUnit\Framework\TestCase;

final class ParallelActionTest extends TestCase
{
    public function testCollectsResultsFromAllCheckers(): void
    {
        $action = new ParallelAction([
            new SuccessChecker(),
            new FailChecker(),
            new SuccessChecker(),
        ], new SafeEventDispatcher());

        $response = $action->run(new Request(CheckTypeEnum::READINESS));

        self::assertFalse($response->success);
        self::assertCount(2, $response->messages);
        self::assertCount(1, $response->errors);
    }

    public function testSkipsCheckersThatReportNoSupport(): void
    {
        $action = new ParallelAction([
            new SuccessChecker(),
            $this->makeReadinessOnlyChecker(),
        ], new SafeEventDispatcher());

        $response = $action->run(new Request(CheckTypeEnum::LIVELINESS));

        // SuccessChecker supports everything → produces its message.
        // The readiness-only checker is filtered out → no second message.
        self::assertTrue($response->success);
        self::assertSame(['success dump check'], $response->messages);
    }

    public function testEmptyCheckersProducesSuccessResponse(): void
    {
        $action = new ParallelAction([], new SafeEventDispatcher());

        $response = $action->run(new Request(CheckTypeEnum::READINESS));

        self::assertTrue($response->success);
        self::assertSame([], $response->messages);
        self::assertSame([], $response->errors);
    }

    public function testFailingCheckerReportsSuccessFalseInEvent(): void
    {
        $dispatcher = new RecordingDispatcherFixture();
        $action = new ParallelAction([new FailChecker()], new SafeEventDispatcher($dispatcher));

        $action->run(new Request(CheckTypeEnum::READINESS));

        $checkerEvent = $dispatcher->events[1];
        self::assertInstanceOf(HealthCheckerCompletedEvent::class, $checkerEvent);
        self::assertFalse($checkerEvent->success);
    }

    public function testEventsCarryTheCheckerServiceIdAndTheProbeType(): void
    {
        $dispatcher = new RecordingDispatcherFixture();
        $action = new ParallelAction(['app.checker.ok' => new SuccessChecker(), 'app.checker.boom' => new ThrowingChecker('boom')], new SafeEventDispatcher($dispatcher));

        $action->run(new Request(CheckTypeEnum::READINESS));

        [, $ok, $boom, $run] = $dispatcher->events;
        self::assertInstanceOf(HealthCheckerCompletedEvent::class, $ok);
        self::assertInstanceOf(HealthCheckerCompletedEvent::class, $boom);
        self::assertInstanceOf(HealthCheckRunCompletedEvent::class, $run);
        self::assertSame(['app.checker.ok', CheckTypeEnum::READINESS, true], [$ok->checkerId, $ok->type, $ok->success]);
        self::assertSame(['app.checker.boom', CheckTypeEnum::READINESS, false], [$boom->checkerId, $boom->type, $boom->success]);
        self::assertSame(CheckTypeEnum::READINESS, $run->type);
    }

    public function testANonCriticalCheckerThatFailedIsReportedAsWarned(): void
    {
        $dispatcher = new RecordingDispatcherFixture();
        $action = new ParallelAction([new NonCriticalCheckerDecorator(new FailChecker()), new SuccessChecker(), new ThrowingChecker('boom')], new SafeEventDispatcher($dispatcher));

        $action->run(new Request(CheckTypeEnum::READINESS));

        [, $failed, $passed, $thrown] = $dispatcher->events;
        self::assertInstanceOf(HealthCheckerCompletedEvent::class, $failed);
        self::assertInstanceOf(HealthCheckerCompletedEvent::class, $passed);
        self::assertInstanceOf(HealthCheckerCompletedEvent::class, $thrown);
        self::assertSame([true, true], [$failed->success, $failed->warned]);
        self::assertSame([true, false], [$passed->success, $passed->warned]);
        self::assertSame([false, false], [$thrown->success, $thrown->warned]);
    }

    public function testRedactsCredentialsInThrownCheckerFailures(): void
    {
        $action = new ParallelAction([new ThrowingChecker('Connection to redis://app:s3cret@redis:6379 refused')], new SafeEventDispatcher());

        $response = $action->run(new Request(CheckTypeEnum::READINESS));

        self::assertSame(
            [sprintf('parallel: %s failed (Connection to redis://***@redis:6379 refused)', ThrowingChecker::class)],
            $response->errors,
        );
    }

    public function testCheckerEventAndFailureNameTheCheckerBehindTheBundleDecorators(): void
    {
        $dispatcher = new RecordingDispatcherFixture();
        $action = new ParallelAction(
            [new TimeoutCheckerDecorator(new ThrowingChecker('boom'), 60_000)],
            new SafeEventDispatcher($dispatcher),
        );

        $response = $action->run(new Request(CheckTypeEnum::READINESS));

        self::assertSame([sprintf('parallel: %s failed (boom)', ThrowingChecker::class)], $response->errors);
        $checkerEvent = $dispatcher->events[1];
        self::assertInstanceOf(HealthCheckerCompletedEvent::class, $checkerEvent);
        self::assertSame(ThrowingChecker::class, $checkerEvent->checkerClass);
    }

    public function testFailureNamesALabeledCheckerBehindTheBundleDecoratorsByItsLabel(): void
    {
        $action = new ParallelAction(
            [new TimeoutCheckerDecorator(new LabeledThrowingChecker('boom'), 60_000)],
            new SafeEventDispatcher(),
        );

        self::assertSame(['parallel: Queue (main) failed (boom)'], $action->run(new Request(CheckTypeEnum::READINESS))->errors);
    }

    public function testFailureNamesTheCheckerByItsClassWhenItsLabelThrows(): void
    {
        $action = new ParallelAction([new BrokenLabelChecker()], new SafeEventDispatcher());

        self::assertSame(
            [sprintf('parallel: %s failed (boom)', BrokenLabelChecker::class)],
            $action->run(new Request(CheckTypeEnum::READINESS))->errors,
        );
    }

    public function testLabelPassesThroughTheWholeDecoratorChain(): void
    {
        $action = new ParallelAction(
            [new NonCriticalCheckerDecorator(new TimeoutCheckerDecorator(new LabeledThrowingChecker('boom'), 60_000))],
            new SafeEventDispatcher(),
        );

        self::assertSame(['Queue (main) failed (boom)'], $action->run(new Request(CheckTypeEnum::READINESS))->warnings);
    }

    public function testNonCriticalCheckerExceptionBecomesAWarning(): void
    {
        $dispatcher = new RecordingDispatcherFixture();
        $action = new ParallelAction(
            [new NonCriticalCheckerDecorator(new TimeoutCheckerDecorator(new ThrowingChecker('boom'), 60_000))],
            new SafeEventDispatcher($dispatcher),
        );

        $response = $action->run(new Request(CheckTypeEnum::READINESS));

        self::assertTrue($response->success);
        self::assertSame([], $response->errors);
        self::assertSame([sprintf('%s failed (boom)', ThrowingChecker::class)], $response->warnings);
        $checkerEvent = $dispatcher->events[1];
        self::assertInstanceOf(HealthCheckerCompletedEvent::class, $checkerEvent);
        self::assertSame(ThrowingChecker::class, $checkerEvent->checkerClass);
    }

    public function testDispatchesEvents(): void
    {
        $dispatcher = new RecordingDispatcherFixture();
        $action = new ParallelAction([new SuccessChecker()], new SafeEventDispatcher($dispatcher));

        $action->run(new Request(CheckTypeEnum::READINESS));

        self::assertCount(3, $dispatcher->events);
        self::assertInstanceOf(HealthCheckRunStartedEvent::class, $dispatcher->events[0]);
        self::assertInstanceOf(HealthCheckerCompletedEvent::class, $dispatcher->events[1]);
        self::assertInstanceOf(HealthCheckRunCompletedEvent::class, $dispatcher->events[2]);
    }

    private function makeReadinessOnlyChecker(): CheckInterface
    {
        return new class implements CheckInterface {
            #[Override]
            public function isSupport(Context $context): bool
            {
                return $context->type === CheckTypeEnum::READINESS;
            }

            #[Override]
            public function check(CheckResult $result, Context $context): CheckResult
            {
                $result->addMessage('readiness-only ran');

                return $result;
            }
        };
    }
}
