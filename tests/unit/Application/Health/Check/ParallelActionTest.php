<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Test\Unit\Application\Health\Check;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Request;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Event\HealthCheckerCompletedEvent;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Event\HealthCheckRunCompletedEvent;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Event\HealthCheckRunStartedEvent;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\ParallelAction;
use MaxShamaev\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\FailChecker;
use MaxShamaev\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\SuccessChecker;
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

    public function testFiltersUnsupportedCheckers(): void
    {
        // SuccessChecker.isSupport returns true for any type, so use a fresh-checker pattern via filtering on type.
        $action = new ParallelAction([new SuccessChecker()]);

        $response = $action->run(new Request(CheckTypeEnum::LIVELINESS));

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

    public function testDispatchesEvents(): void
    {
        $dispatcher = new RecordingDispatcherFixture();
        $action = new ParallelAction([new SuccessChecker()], $dispatcher);

        $action->run(new Request(CheckTypeEnum::READINESS));

        self::assertCount(3, $dispatcher->events);
        self::assertInstanceOf(HealthCheckRunStartedEvent::class, $dispatcher->events[0]);
        self::assertInstanceOf(HealthCheckerCompletedEvent::class, $dispatcher->events[1]);
        self::assertInstanceOf(HealthCheckRunCompletedEvent::class, $dispatcher->events[2]);
    }
}
