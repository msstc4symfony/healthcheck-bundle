<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Test\Unit\Application\Health\Check;

use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Action;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\Request;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Event\HealthCheckerCompletedEvent;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Event\HealthCheckRunCompletedEvent;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Event\HealthCheckRunStartedEvent;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Event\SafeEventDispatcher;
use MSSTC4PHP\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\FailChecker;
use MSSTC4PHP\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\SuccessChecker;
use MSSTC4PHP\HealthCheckBundle\Test\Mock\Application\Health\Check\Event\RecordingDispatcherFixture;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

final class ActionEventsTest extends TestCase
{
    public function testDispatchesStartedAndCompletedEvents(): void
    {
        $dispatcher = new RecordingDispatcherFixture();
        $action = new Action([new SuccessChecker()], new SafeEventDispatcher($dispatcher));

        $action->run(new Request(CheckTypeEnum::READINESS));

        self::assertCount(3, $dispatcher->events);
        self::assertInstanceOf(HealthCheckRunStartedEvent::class, $dispatcher->events[0]);
        self::assertInstanceOf(HealthCheckerCompletedEvent::class, $dispatcher->events[1]);
        self::assertInstanceOf(HealthCheckRunCompletedEvent::class, $dispatcher->events[2]);

        $checkerEvent = $dispatcher->events[1];
        self::assertSame(SuccessChecker::class, $checkerEvent->checkerClass);
        self::assertTrue($checkerEvent->success);
        self::assertGreaterThanOrEqual(0, $checkerEvent->durationMs);
    }

    public function testCheckerEventCarriesFailureFlag(): void
    {
        $dispatcher = new RecordingDispatcherFixture();
        $action = new Action([new FailChecker()], new SafeEventDispatcher($dispatcher));

        $action->run(new Request(CheckTypeEnum::READINESS));

        $checkerEvent = $dispatcher->events[1];
        self::assertInstanceOf(HealthCheckerCompletedEvent::class, $checkerEvent);
        self::assertSame(FailChecker::class, $checkerEvent->checkerClass);
        self::assertFalse($checkerEvent->success);
    }

    public function testNoDispatchWhenDispatcherNull(): void
    {
        $action = new Action([new SuccessChecker()]);

        $response = $action->run(new Request(CheckTypeEnum::READINESS));

        self::assertTrue($response->success);
    }

    public function testBuggyListenerDoesNotFailProbe(): void
    {
        $dispatcher = new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                throw new RuntimeException('listener exploded');
            }
        };
        $action = new Action([new SuccessChecker()], new SafeEventDispatcher($dispatcher));

        $response = $action->run(new Request(CheckTypeEnum::READINESS));

        self::assertTrue($response->success);
        self::assertSame(['success dump check'], $response->messages);
    }
}
