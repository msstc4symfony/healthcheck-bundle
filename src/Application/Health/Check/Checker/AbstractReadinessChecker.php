<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\CredentialRedactor;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Run\SharedProbe;
use Override;
use Throwable;

abstract readonly class AbstractReadinessChecker implements CheckInterface, LabeledCheckerInterface
{
    #[Override]
    final public function isSupport(Context $context): bool
    {
        return $context->type === CheckTypeEnum::READINESS;
    }

    #[Override]
    final public function check(CheckResult $result, Context $context): CheckResult
    {
        try {
            $skipReason = $this->skipReason();
            if ($skipReason !== null) {
                $result->addMessage(sprintf('%s skipped (%s)', $this->label(), $skipReason));

                return $result;
            }

            $key = $this->sharedProbeKey();
            $shared = $key === null ? null : $context->sharedProbes->reuse($key);
            if ($shared instanceof SharedProbe) {
                $shared->reportTo($result, $this->label(), null);

                return $result;
            }

            $startedAt = hrtime(true);
            try {
                $detail = $this->doCheck();
            } catch (Throwable $e) {
                if ($key !== null) {
                    $context->sharedProbes->record($key, SharedProbe::failed($this->label(), CredentialRedactor::redact($e->getMessage()), $startedAt, hrtime(true)));
                }

                throw $e;
            }

            if ($key !== null) {
                $context->sharedProbes->record($key, SharedProbe::passed($this->label(), $startedAt, hrtime(true)));
            }

            $result->addMessage(sprintf('%s passed', $this->label()) . ($detail !== null ? sprintf(' (%s)', CredentialRedactor::redact($detail)) : ''));
        } catch (Throwable $e) {
            $result->addError(sprintf('%s failed (%s)', $this->label(), CredentialRedactor::redact($e->getMessage())));
        }

        return $result;
    }

    #[Override]
    public function checkerLabel(): string
    {
        return $this->label();
    }

    /**
     * Probe the underlying infrastructure. Throw any Throwable to mark the check as failed.
     *
     * @return string|null detail appended to the success message as "<label> passed (<detail>)", credentials masked
     */
    abstract protected function doCheck(): ?string;

    /**
     * Human-readable identifier for log/HTTP output, e.g. "DB connection (default)".
     */
    abstract protected function label(): string;

    /**
     * Override to short-circuit the probe with a "skipped (<reason>)" message. Default: always probe.
     * A Throwable thrown here fails the check like one thrown by doCheck().
     *
     * Used by checkers whose underlying driver supports only a destructive or unavailable probe
     * in certain runtime configurations (APCu in CLI, NullAdapter, Messenger transports without
     * MessageCountAwareInterface, etc.).
     */
    protected function skipReason(): ?string
    {
        return null;
    }

    /**
     * Override when several checkers probe one backend (e.g. cache pools on one Redis connection): within one
     * run only the first checker with a given key really probes, the others report its outcome without probing.
     * Only the outcome of doCheck() is shared, not a failure to build a checker service of your own.
     *
     * @return non-empty-string|null
     */
    protected function sharedProbeKey(): ?string
    {
        return null;
    }
}
