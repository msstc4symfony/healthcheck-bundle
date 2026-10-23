<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Application\Health\Check\Run;

use InvalidArgumentException;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Run\ProbeBudget;
use PHPUnit\Framework\TestCase;

final class ProbeBudgetTest extends TestCase
{
    private const MS = 1_000_000;

    public function testIsNotExhaustedWithinTheLimit(): void
    {
        self::assertNull($this->started()->exhaustedMessage('Redis connection', 100 * self::MS + 499 * self::MS));
    }

    public function testIsNotExhaustedExactlyAtTheLimit(): void
    {
        self::assertNull($this->started()->exhaustedMessage('Redis connection', 100 * self::MS + 500 * self::MS));
    }

    public function testIsExhaustedPastTheLimit(): void
    {
        self::assertSame(
            'Redis connection skipped: probe budget exhausted (501 ms > 500 ms)',
            $this->started()->exhaustedMessage('Redis connection', 100 * self::MS + 501 * self::MS),
        );
    }

    public function testElapsedTimeIsTruncatedToWholeMilliseconds(): void
    {
        $budget = new ProbeBudget(10 * self::MS, 1000);
        $budget->checkerFinished();

        self::assertSame(
            'X skipped: probe budget exhausted (1500 ms > 1000 ms)',
            $budget->exhaustedMessage('X', 10 * self::MS + 1500 * self::MS + 999_999),
        );
    }

    public function testNeverSkipsBeforeTheFirstCheckerHasFinished(): void
    {
        $budget = new ProbeBudget(0, 1);

        self::assertNull($budget->exhaustedMessage('First', 10_000 * self::MS));

        $budget->checkerFinished();

        self::assertNotNull($budget->exhaustedMessage('Second', 10_000 * self::MS));
    }

    public function testNoBudgetWithoutALimit(): void
    {
        self::assertNull(ProbeBudget::start(null, 1));
        self::assertEquals(new ProbeBudget(1, 50), ProbeBudget::start(50, 1));
    }

    public function testAcceptsTheSmallestLimit(): void
    {
        self::assertSame(1, (new ProbeBudget(1, 1))->limitMs);
    }

    public function testRejectsANonPositiveLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Reason: the constructor guards a contract the type system already states.
        // @phpstan-ignore argument.type
        new ProbeBudget(1, 0);
    }

    private function started(): ProbeBudget
    {
        $budget = new ProbeBudget(100 * self::MS, 500);
        $budget->checkerFinished();

        return $budget;
    }
}
