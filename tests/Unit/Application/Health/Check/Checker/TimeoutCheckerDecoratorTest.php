<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use Closure;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\LabeledCheckerInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\TimeoutCheckerDecorator;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Run\ProbeBudget;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\ClosureReadinessCheckerFixture;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\LabeledThrowingChecker;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TimeoutCheckerDecoratorTest extends TestCase
{
    public function testIsSupportDelegatesToInner(): void
    {
        $decorator = new TimeoutCheckerDecorator($this->makeInner(static fn (): null => null), 1000);

        self::assertTrue($decorator->isSupport(new Context(CheckTypeEnum::READINESS)));
    }

    public function testPassesThroughFastChecker(): void
    {
        $inner = $this->makeInner(static function (CheckResult $r): void {
            $r->addMessage('Inner passed');
        });

        $result = (new TimeoutCheckerDecorator($inner, 1000))
            ->check(new CheckResult(), new Context(CheckTypeEnum::READINESS))
        ;

        self::assertSame(['Inner passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testReportsErrorWhenInnerExceedsBudget(): void
    {
        $inner = $this->makeInner(static function (CheckResult $r): void {
            usleep(60_000); // 60ms
            $r->addMessage('Inner would have passed');
        });

        $result = (new TimeoutCheckerDecorator($inner, 10)) // 10ms budget
            ->check(new CheckResult(), new Context(CheckTypeEnum::READINESS))
        ;

        self::assertSame([], $result->messages, 'inner trailing messages must be stripped');
        self::assertCount(1, $result->errors);
        self::assertStringContainsString('exceeded budget', $result->errors[0]);
    }

    public function testPreservesEarlierResultEntries(): void
    {
        $result = new CheckResult();
        $result->addMessage('Earlier message');
        $result->addError('Earlier error');

        $inner = $this->makeInner(static function (CheckResult $r): void {
            usleep(60_000);
            $r->addMessage('Inner');
        });

        $result = (new TimeoutCheckerDecorator($inner, 10))
            ->check($result, new Context(CheckTypeEnum::READINESS))
        ;

        self::assertSame(['Earlier message'], $result->messages);
        self::assertCount(2, $result->errors);
        self::assertSame('Earlier error', $result->errors[0]);
        self::assertStringContainsString('exceeded budget', $result->errors[1]);
    }

    public function testNamesTheCheckerThatExceededTheBudget(): void
    {
        $inner = $this->makeInner(static function (): void {
            usleep(20_000);
        });

        $result = (new TimeoutCheckerDecorator($inner, 1))->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertMatchesRegularExpression(
            sprintf('/^%s exceeded budget \\(\\d+ ms > 1 ms\\)$/', preg_quote($inner::class, '/')),
            $result->errors[0],
        );
    }

    public function testNamesALabeledCheckerThatExceededTheBudgetByItsLabel(): void
    {
        $inner = new ClosureReadinessCheckerFixture(static function (): null {
            usleep(20_000);

            return null;
        }, null);

        $result = (new TimeoutCheckerDecorator($inner, 1))->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertMatchesRegularExpression('/^Probe \\(test\\) exceeded budget \\(\\d+ ms > 1 ms\\)$/', $result->errors[0]);
    }

    public function testDoesNotRunTheInnerOnceTheProbeBudgetIsExhausted(): void
    {
        $ran = false;
        $inner = $this->makeInner(static function () use (&$ran): void {
            $ran = true;
        });
        $context = new Context(CheckTypeEnum::READINESS, [], $this->budgetAfterAFinishedChecker(100));

        $result = (new TimeoutCheckerDecorator($inner, 1000))->check(new CheckResult(), $context);

        self::assertFalse($ran);
        self::assertCount(1, $result->errors);
        self::assertMatchesRegularExpression(
            sprintf('/^%s skipped: probe budget exhausted \\(\\d+ ms > 100 ms\\)$/', preg_quote($inner::class, '/')),
            $result->errors[0],
        );
    }

    public function testRunsTheFirstCheckerOfTheRunEvenWithTheBudgetSpent(): void
    {
        $inner = $this->makeInner(static function (CheckResult $r): void {
            $r->addMessage('ran');
        });
        $context = new Context(CheckTypeEnum::READINESS, [], new ProbeBudget(hrtime(true) - 10_000_000_000, 1));

        $result = (new TimeoutCheckerDecorator($inner, 1000))->check(new CheckResult(), $context);

        self::assertSame(['ran'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCountsASkippedRunOfTheNextCheckerOnlyAfterTheFirstFinished(): void
    {
        $budget = new ProbeBudget(hrtime(true) - 10_000_000_000, 1);
        $context = new Context(CheckTypeEnum::READINESS, [], $budget);
        $inner = $this->makeInner(static function (): void {
        });
        $decorator = new TimeoutCheckerDecorator($inner, 1000);

        self::assertSame([], $decorator->check(new CheckResult(), $context)->errors);
        self::assertCount(1, $decorator->check(new CheckResult(), $context)->errors);
    }

    public function testCountsACheckerThatThrewAsFinished(): void
    {
        $budget = new ProbeBudget(hrtime(true) - 10_000_000_000, 1);
        $context = new Context(CheckTypeEnum::READINESS, [], $budget);

        try {
            (new TimeoutCheckerDecorator(new LabeledThrowingChecker('boom'), 1000))->check(new CheckResult(), $context);
            self::fail('the checker should have thrown');
        } catch (RuntimeException) {
        }

        self::assertNotNull($budget->exhaustedMessage('Next', hrtime(true)));
    }

    public function testRunsTheInnerWhileTheProbeBudgetHolds(): void
    {
        $inner = $this->makeInner(static function (CheckResult $r): void {
            $r->addMessage('ran');
        });
        $context = new Context(CheckTypeEnum::READINESS, [], new ProbeBudget(hrtime(true), 60_000));

        $result = (new TimeoutCheckerDecorator($inner, 1000))->check(new CheckResult(), $context);

        self::assertSame(['ran'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testExposesTheLabelOfTheCheckerItWraps(): void
    {
        self::assertSame(LabeledThrowingChecker::LABEL, (new TimeoutCheckerDecorator(new LabeledThrowingChecker('boom'), 10))->checkerLabel());
    }

    #[DataProvider('unusableLabels')]
    public function testNamesTheCheckerByItsClassWhenItsLabelIsUnusable(Closure $label): void
    {
        $inner = $this->makeSlowLabeledInner($label);

        $result = (new TimeoutCheckerDecorator($inner, 1))->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertStringStartsWith($inner::class . ' exceeded budget (', $result->errors[0]);
    }

    /**
     * @return iterable<string, array{Closure(): string}>
     */
    public static function unusableLabels(): iterable
    {
        yield 'throwing' => [static fn (): string => throw new RuntimeException('label broken')];
        yield 'empty' => [static fn (): string => ''];
    }

    /**
     * @param int<1, max> $limitMs
     */
    private function budgetAfterAFinishedChecker(int $limitMs): ProbeBudget
    {
        $budget = new ProbeBudget(hrtime(true) - 10_000_000_000, $limitMs);
        $budget->checkerFinished();

        return $budget;
    }

    private function makeInner(Closure $body): CheckInterface
    {
        return new class($body) implements CheckInterface {
            public function __construct(private readonly Closure $body)
            {
            }

            #[Override]
            public function isSupport(Context $context): bool
            {
                return true;
            }

            #[Override]
            public function check(CheckResult $result, Context $context): CheckResult
            {
                ($this->body)($result);

                return $result;
            }
        };
    }

    /**
     * @param Closure(): string $label
     */
    private function makeSlowLabeledInner(Closure $label): CheckInterface
    {
        return new class($label) implements CheckInterface, LabeledCheckerInterface {
            /**
             * @param Closure(): string $label
             */
            public function __construct(private readonly Closure $label)
            {
            }

            #[Override]
            public function isSupport(Context $context): bool
            {
                return true;
            }

            #[Override]
            public function check(CheckResult $result, Context $context): CheckResult
            {
                usleep(20_000);

                return $result;
            }

            #[Override]
            public function checkerLabel(): string
            {
                return ($this->label)();
            }
        };
    }
}
