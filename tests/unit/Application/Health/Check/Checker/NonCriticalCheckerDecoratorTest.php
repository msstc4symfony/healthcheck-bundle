<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use Closure;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\NonCriticalCheckerDecorator;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PHPUnit\Framework\TestCase;

final class NonCriticalCheckerDecoratorTest extends TestCase
{
    public function testPassesMessagesThrough(): void
    {
        $inner = $this->makeInner(static function (CheckResult $r): void {
            $r->addMessage('Inner passed');
        });

        $result = new NonCriticalCheckerDecorator($inner)
            ->check(new CheckResult(), new Context(CheckTypeEnum::READINESS))
        ;

        self::assertSame(['Inner passed'], $result->messages);
        self::assertSame([], $result->errors);
        self::assertSame([], $result->warnings);
    }

    public function testDemotesInnerErrorIntoWarning(): void
    {
        $inner = $this->makeInner(static function (CheckResult $r): void {
            $r->addError('something broke');
        });

        $result = new NonCriticalCheckerDecorator($inner)
            ->check(new CheckResult(), new Context(CheckTypeEnum::READINESS))
        ;

        self::assertSame([], $result->errors);
        self::assertSame(['something broke'], $result->warnings);
    }

    public function testPreservesEarlierErrors(): void
    {
        $result = new CheckResult();
        $result->addError('earlier critical failure');

        $inner = $this->makeInner(static function (CheckResult $r): void {
            $r->addError('demoted error');
        });

        $result = new NonCriticalCheckerDecorator($inner)
            ->check($result, new Context(CheckTypeEnum::READINESS))
        ;

        self::assertSame(['earlier critical failure'], $result->errors);
        self::assertSame(['demoted error'], $result->warnings);
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
