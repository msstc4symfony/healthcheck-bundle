<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use Closure;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\AbstractReadinessChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AbstractReadinessCheckerTest extends TestCase
{
    public function testIsSupportReadinessOnly(): void
    {
        $checker = $this->makeChecker(static function (): void {});

        self::assertTrue($checker->isSupport(new Context(CheckTypeEnum::READINESS)));
        self::assertFalse($checker->isSupport(new Context(CheckTypeEnum::LIVELINESS)));
    }

    public function testCheckAddsPassedMessageOnSuccess(): void
    {
        $checker = $this->makeChecker(static function (): void {});

        $result = $checker->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Probe (test) passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCheckAddsErrorWithExceptionMessageOnFailure(): void
    {
        $checker = $this->makeChecker(static function (): never {
            throw new RuntimeException('database is gone');
        });

        $result = $checker->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame([], $result->messages);
        self::assertSame(['Probe (test) failed (database is gone)'], $result->errors);
    }

    private function makeChecker(Closure $probe): AbstractReadinessChecker
    {
        return new readonly class($probe) extends AbstractReadinessChecker {
            public function __construct(private Closure $probe)
            {
            }

            protected function doCheck(): void
            {
                ($this->probe)();
            }

            protected function label(): string
            {
                return 'Probe (test)';
            }
        };
    }
}
