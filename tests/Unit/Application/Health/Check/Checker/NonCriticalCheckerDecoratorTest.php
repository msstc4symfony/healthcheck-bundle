<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use Closure;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\NonCriticalCheckerDecorator;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\ThrowingChecker;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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

    public function testDemotesThrownExceptionIntoRedactedWarningNamingTheChecker(): void
    {
        $result = new NonCriticalCheckerDecorator(new ThrowingChecker('Connection to redis://app:s3cret@redis:6379 refused'))
            ->check(new CheckResult(), new Context(CheckTypeEnum::READINESS))
        ;

        self::assertSame([], $result->errors);
        self::assertSame(
            [sprintf('%s failed (Connection to redis://***@redis:6379 refused)', ThrowingChecker::class)],
            $result->warnings,
        );
    }

    public function testDiscardsPartialInnerOutputWhenTheInnerThrows(): void
    {
        $result = new CheckResult();
        $result->addMessage('earlier message');
        $result->addError('earlier critical failure');
        $result->addWarning('earlier warning');

        $inner = $this->makeInner(static function (CheckResult $r): never {
            $r->addMessage('partial message');
            $r->addError('partial error');
            $r->addWarning('partial warning');

            throw new RuntimeException('boom');
        });

        $result = new NonCriticalCheckerDecorator($inner)
            ->check($result, new Context(CheckTypeEnum::READINESS))
        ;

        self::assertSame(['earlier message'], $result->messages);
        self::assertSame(['earlier critical failure'], $result->errors);
        self::assertSame(['earlier warning', sprintf('%s failed (boom)', $inner::class)], $result->warnings);
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
