<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Application\Health\Check;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Action;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\DeferredReadinessCheckerDecorator;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\NonCriticalCheckerDecorator;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\TimeoutCheckerDecorator;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Request;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Event\HealthCheckerCompletedEvent;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Event\HealthCheckRunCompletedEvent;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Event\HealthCheckRunStartedEvent;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Event\SafeEventDispatcher;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\FailChecker;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\SuccessChecker;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Event\RecordingDispatcherFixture;
use Override;
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

    public function testCheckerEventNamesTheCheckerBehindTheBundleDecorators(): void
    {
        $dispatcher = new RecordingDispatcherFixture();
        $decorated = new NonCriticalCheckerDecorator(new DeferredReadinessCheckerDecorator(
            static fn (): CheckInterface => new TimeoutCheckerDecorator(new SuccessChecker(), 60_000),
            'Success',
            SuccessChecker::class,
        ));
        $action = new Action([$decorated, new TimeoutCheckerDecorator(new FailChecker(), 60_000)], new SafeEventDispatcher($dispatcher));

        $action->run(new Request(CheckTypeEnum::READINESS));

        $classes = array_map(
            static fn (object $event): ?string => $event instanceof HealthCheckerCompletedEvent ? $event->checkerClass : null,
            $dispatcher->events,
        );
        self::assertSame([null, SuccessChecker::class, FailChecker::class, null], $classes);
    }

    public function testEventsCarryTheCheckerServiceIdAndTheProbeType(): void
    {
        $dispatcher = new RecordingDispatcherFixture();
        $action = new Action(['app.checker.ok' => new SuccessChecker(), 'app.checker.down' => new FailChecker()], new SafeEventDispatcher($dispatcher));

        $action->run(new Request(CheckTypeEnum::READINESS));

        [, $ok, $down, $run] = $dispatcher->events;
        self::assertInstanceOf(HealthCheckerCompletedEvent::class, $ok);
        self::assertInstanceOf(HealthCheckerCompletedEvent::class, $down);
        self::assertInstanceOf(HealthCheckRunCompletedEvent::class, $run);
        self::assertSame(['app.checker.ok', CheckTypeEnum::READINESS], [$ok->checkerId, $ok->type]);
        self::assertSame(['app.checker.down', CheckTypeEnum::READINESS], [$down->checkerId, $down->type]);
        self::assertSame(CheckTypeEnum::READINESS, $run->type);
    }

    public function testANonCriticalCheckerThatFailedIsReportedAsWarned(): void
    {
        $dispatcher = new RecordingDispatcherFixture();
        $action = new Action([new NonCriticalCheckerDecorator(new FailChecker()), new SuccessChecker()], new SafeEventDispatcher($dispatcher));

        $action->run(new Request(CheckTypeEnum::READINESS));

        [, $failed, $passed] = $dispatcher->events;
        self::assertInstanceOf(HealthCheckerCompletedEvent::class, $failed);
        self::assertInstanceOf(HealthCheckerCompletedEvent::class, $passed);
        self::assertSame([true, true], [$failed->success, $failed->warned]);
        self::assertSame([true, false], [$passed->success, $passed->warned]);
    }

    public function testCheckerIdIsNullForCheckersGivenAsAList(): void
    {
        $dispatcher = new RecordingDispatcherFixture();
        $action = new Action([new SuccessChecker()], new SafeEventDispatcher($dispatcher));

        $action->run(new Request(CheckTypeEnum::LIVELINESS));

        $checkerEvent = $dispatcher->events[1];
        self::assertInstanceOf(HealthCheckerCompletedEvent::class, $checkerEvent);
        self::assertNull($checkerEvent->checkerId);
        self::assertSame(CheckTypeEnum::LIVELINESS, $checkerEvent->type);
    }

    public function testNoDispatchWhenDispatcherNull(): void
    {
        $action = new Action([new SuccessChecker()], new SafeEventDispatcher());

        $response = $action->run(new Request(CheckTypeEnum::READINESS));

        self::assertTrue($response->success);
    }

    public function testBuggyListenerDoesNotFailProbe(): void
    {
        $dispatcher = new class implements EventDispatcherInterface {
            #[Override]
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
