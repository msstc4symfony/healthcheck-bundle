<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

use Closure;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\CredentialRedactor;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Run\SharedProbe;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Throwable;

/**
 * Builds a readiness-only checker (and so its target client) on first use inside the probe.
 *
 * The checker iterable is materialised before isSupport() filters by type, so a client whose
 * constructor throws (SemaphoreStore without ext-sysvsem, a bad DSN) would otherwise fail every
 * probe, liveliness included. Deferred, the failure stays this check's readiness error.
 *
 * A checker that shares its backend with others (see AbstractReadinessChecker::sharedProbeKey()) reports
 * the outcome of an earlier probe of that backend instead of building its target again, and records a
 * construction failure for the ones after it.
 *
 * The probe budget is checked here and in TimeoutCheckerDecorator on purpose: this one skips before the target
 * is built, that one covers checkers that are not deferred.
 *
 * @internal wired by HealthCheckerDeferredConstructionPass
 */
#[Exclude]
final readonly class DeferredReadinessCheckerDecorator implements CheckInterface, CheckerDecoratorInterface, LabeledCheckerInterface
{
    /**
     * @param Closure(): CheckInterface $factory
     * @param non-empty-string $label the checker's own label, reported when construction fails
     * @param class-string<CheckInterface> $checkerClass known at compile time, so events name the checker even when it cannot be built
     * @param non-empty-string|null $shareKey the checker's sharedProbeKey(), known at compile time
     * @param positive-int|null $timeoutMs the budget of the Timeout decorator inside the factory, applied to a reused probe
     */
    public function __construct(
        private Closure $factory,
        private string $label,
        private string $checkerClass,
        private ?string $shareKey = null,
        private ?int $timeoutMs = null,
    ) {
    }

    #[Override]
    public function isSupport(Context $context): bool
    {
        return $context->type === CheckTypeEnum::READINESS;
    }

    #[Override]
    public function check(CheckResult $result, Context $context): CheckResult
    {
        $skipped = $context->budget?->exhaustedMessage($this->label, hrtime(true));
        if ($skipped !== null) {
            $result->addError($skipped);

            return $result;
        }

        $shared = $this->shareKey === null ? null : $context->sharedProbes->outcomeOf($this->shareKey);
        if ($shared instanceof SharedProbe) {
            $shared->reportTo($result, $this->label, $this->timeoutMs);

            return $result;
        }

        $startedAt = hrtime(true);
        try {
            $inner = ($this->factory)();
        } catch (Throwable $e) {
            $message = CredentialRedactor::redact($e->getMessage());
            if ($this->shareKey !== null) {
                $context->sharedProbes->record($this->shareKey, SharedProbe::failed($this->label, $message, $startedAt, hrtime(true)));
            }
            $result->addError(sprintf('%s failed (%s)', $this->label, $message));
            $context->budget?->checkerFinished();

            return $result;
        }

        return $inner->check($result, $context);
    }

    #[Override]
    public function checkerLabel(): string
    {
        return $this->label;
    }

    #[Override]
    public function decoratedCheckerClass(): string
    {
        return $this->checkerClass;
    }
}
