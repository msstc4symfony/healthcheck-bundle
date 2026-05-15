<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use Closure;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\TimeoutCheckerDecorator;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PHPUnit\Framework\TestCase;

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

        $result = new TimeoutCheckerDecorator($inner, 1000)
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

        $result = new TimeoutCheckerDecorator($inner, 10) // 10ms budget
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

        $result = new TimeoutCheckerDecorator($inner, 10)
            ->check($result, new Context(CheckTypeEnum::READINESS))
        ;

        self::assertSame(['Earlier message'], $result->messages);
        self::assertCount(2, $result->errors);
        self::assertSame('Earlier error', $result->errors[0]);
        self::assertStringContainsString('exceeded budget', $result->errors[1]);
    }

    private function makeInner(Closure $body): CheckInterface
    {
        return new readonly class($body) implements CheckInterface {
            public function __construct(private Closure $body)
            {
            }

            public function isSupport(Context $context): bool
            {
                return true;
            }

            public function check(CheckResult $result, Context $context): CheckResult
            {
                ($this->body)($result);

                return $result;
            }
        };
    }
}
