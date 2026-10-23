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
 *
 * The probe budget is also checked in DeferredReadinessCheckerDecorator, on purpose: it skips before a
 * deferred checker is built, while checkers that are not deferred are covered here.
 *
 * @internal
 */
#[Exclude]
final readonly class TimeoutCheckerDecorator implements CheckInterface, CheckerDecoratorInterface, LabeledCheckerInterface
{
    /**
     * @param positive-int $timeoutMs
     */
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
        $skipped = $context->budget?->exhaustedMessage($this->checkerLabel(), hrtime(true));
        if ($skipped !== null) {
            $result->addError($skipped);

            return $result;
        }

        $startedAt = microtime(true);

        $messagesBefore = count($result->messages);
        $errorsBefore = count($result->errors);
        $warningsBefore = count($result->warnings);

        try {
            $result = $this->inner->check($result, $context);
        } finally {
            $context->budget?->checkerFinished();
        }

        // A checker that reused a shared probe took no time itself, but its backend was as slow as that probe.
        $elapsedMs = max((int) ((microtime(true) - $startedAt) * 1000), $context->sharedProbes->takeReusedDurationMs() ?? 0);
        if ($elapsedMs <= $this->timeoutMs) {
            return $result;
        }

        // Strip messages/errors/warnings added by the inner during this call; report timeout instead.
        $result->resetTrailing($messagesBefore, $errorsBefore, $warningsBefore);
        $result->addError(sprintf(
            '%s exceeded budget (%d ms > %d ms)',
            $this->checkerLabel(),
            $elapsedMs,
            $this->timeoutMs,
        ));

        return $result;
    }

    #[Override]
    public function checkerLabel(): string
    {
        return CheckerLabel::of($this->inner);
    }

    #[Override]
    public function decoratedCheckerClass(): string
    {
        return CheckerClass::of($this->inner);
    }
}
