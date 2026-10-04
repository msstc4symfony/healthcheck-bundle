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
        $checker = $this->makeChecker(static fn (): null => null);

        self::assertTrue($checker->isSupport(new Context(CheckTypeEnum::READINESS)));
        self::assertFalse($checker->isSupport(new Context(CheckTypeEnum::LIVELINESS)));
    }

    public function testCheckAddsPassedMessageOnSuccess(): void
    {
        $checker = $this->makeChecker(static fn (): null => null);

        $result = $checker->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Probe (test) passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCheckAppendsTheDetailReturnedByTheProbe(): void
    {
        $checker = $this->makeChecker(static fn (): string => 'cluster status: green');

        $result = $checker->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Probe (test) passed (cluster status: green)'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCheckRedactsCredentialsInTheProbeDetail(): void
    {
        $checker = $this->makeChecker(static fn (): string => 'primary redis://admin:s3cret@redis:6379/0');

        $result = $checker->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Probe (test) passed (primary redis://***@redis:6379/0)'], $result->messages);
    }

    public function testCheckReportsSkipReasonWithoutProbing(): void
    {
        $checker = $this->makeChecker(static function (): never {
            throw new RuntimeException('must not probe');
        }, static fn (): string => 'nothing to probe');

        $result = $checker->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Probe (test) skipped (nothing to probe)'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCheckReportsAFailingSkipReasonAsFailure(): void
    {
        $checker = $this->makeChecker(static fn (): null => null, static function (): never {
            throw new RuntimeException('config unreadable');
        });

        $result = $checker->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame([], $result->messages);
        self::assertSame(['Probe (test) failed (config unreadable)'], $result->errors);
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

    public function testCheckRedactsCredentialsInFailureMessage(): void
    {
        $checker = $this->makeChecker(static function (): never {
            throw new RuntimeException('Connection to "redis://admin:s3cret@redis:6379/0" refused');
        });

        $result = $checker->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Probe (test) failed (Connection to "redis://***@redis:6379/0" refused)'], $result->errors);
    }

    /**
     * @param Closure(): ?string $probe
     * @param (Closure(): ?string)|null $skipReason
     */
    private function makeChecker(Closure $probe, ?Closure $skipReason = null): AbstractReadinessChecker
    {
        return new readonly class($probe, $skipReason) extends AbstractReadinessChecker {
            /**
             * @param Closure(): ?string $probe
             * @param (Closure(): ?string)|null $skip
             */
            public function __construct(private Closure $probe, private ?Closure $skip)
            {
            }

            protected function doCheck(): ?string
            {
                return ($this->probe)();
            }

            protected function skipReason(): ?string
            {
                return $this->skip instanceof Closure ? ($this->skip)() : null;
            }

            protected function label(): string
            {
                return 'Probe (test)';
            }
        };
    }
}
