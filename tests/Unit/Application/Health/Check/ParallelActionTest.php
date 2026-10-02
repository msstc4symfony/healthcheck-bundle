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
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\FailChecker;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\SuccessChecker;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\ThrowingChecker;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Event\RecordingDispatcherFixture;
use PHPUnit\Framework\TestCase;

final class ParallelActionTest extends TestCase
{
    public function testCollectsResultsFromAllCheckers(): void
    {
        $action = new ParallelAction([
            new SuccessChecker(),
            new FailChecker(),
            new SuccessChecker(),
        ]);

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
        ]);

        $response = $action->run(new Request(CheckTypeEnum::LIVELINESS));

        // SuccessChecker supports everything → produces its message.
        // The readiness-only checker is filtered out → no second message.
        self::assertTrue($response->success);
        self::assertSame(['success dump check'], $response->messages);
    }

    public function testEmptyCheckersProducesSuccessResponse(): void
    {
        $action = new ParallelAction([]);

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

    public function testRedactsCredentialsInThrownCheckerFailures(): void
    {
        $action = new ParallelAction([new ThrowingChecker('Connection to redis://app:s3cret@redis:6379 refused')]);

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
        return new readonly class implements CheckInterface {
            public function isSupport(Context $context): bool
            {
                return $context->type === CheckTypeEnum::READINESS;
            }

            public function check(CheckResult $result, Context $context): CheckResult
            {
                $result->addMessage('readiness-only ran');

                return $result;
            }
        };
    }
}
