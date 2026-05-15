<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Test\Unit\Application\Health\Check;

use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\Request;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Event\HealthCheckerCompletedEvent;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Event\HealthCheckRunCompletedEvent;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Event\HealthCheckRunStartedEvent;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Event\SafeEventDispatcher;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\ParallelAction;
use MSSTC4PHP\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\FailChecker;
use MSSTC4PHP\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\SuccessChecker;
use MSSTC4PHP\HealthCheckBundle\Test\Mock\Application\Health\Check\Event\RecordingDispatcherFixture;
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
