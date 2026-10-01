<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Measures wall-clock duration of the wrapped checker. Reports an error
 * if execution exceeded the configured budget.
 *
 * Note: in synchronous PHP this is post-hoc detection only — the inner check
 * still runs to completion. Real interrupting requires either pcntl_alarm
 * (CLI-only, second resolution) or driver-level async I/O.
 */
#[Exclude]
final readonly class TimeoutCheckerDecorator implements CheckInterface
{
    public function __construct(
        private CheckInterface $inner,
        private int $timeoutMs,
    ) {
    }

    #[Override]
    public function isSupport(Context $context): bool
    {
        return $this->inner->isSupport($context);
    }

    #[Override]
    public function check(CheckResult $result, Context $context): CheckResult
    {
        $startedAt = microtime(true);
        $messagesBefore = count($result->messages);
        $errorsBefore = count($result->errors);
        $warningsBefore = count($result->warnings);

        $result = $this->inner->check($result, $context);

        $elapsedMs = (int) ((microtime(true) - $startedAt) * 1000);
        if ($elapsedMs <= $this->timeoutMs) {
            return $result;
        }

        // Strip messages/errors/warnings added by the inner during this call; report timeout instead.
        $result->resetTrailing($messagesBefore, $errorsBefore, $warningsBefore);
        $result->addError(sprintf(
            '%s exceeded budget (%d ms > %d ms)',
            $this->inner::class,
            $elapsedMs,
            $this->timeoutMs,
        ));

        return $result;
    }
}
