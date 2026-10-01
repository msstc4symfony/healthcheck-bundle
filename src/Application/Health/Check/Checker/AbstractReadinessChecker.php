<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\CredentialRedactor;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Override;
use Throwable;

abstract readonly class AbstractReadinessChecker implements CheckInterface
{
    #[Override]
    final public function isSupport(Context $context): bool
    {
        return $context->type === CheckTypeEnum::READINESS;
    }

    #[Override]
    final public function check(CheckResult $result, Context $context): CheckResult
    {
        $skipReason = $this->skipReason();
        if ($skipReason !== null) {
            $result->addMessage(sprintf('%s skipped (%s)', $this->label(), $skipReason));

            return $result;
        }

        try {
            $this->doCheck();
            $result->addMessage(sprintf('%s passed', $this->label()));
        } catch (Throwable $e) {
            $result->addError(sprintf('%s failed (%s)', $this->label(), CredentialRedactor::redact($e->getMessage())));
        }

        return $result;
    }

    /**
     * Probe the underlying infrastructure. Throw any Throwable to mark the check as failed.
     */
    abstract protected function doCheck(): void;

    /**
     * Human-readable identifier for log/HTTP output, e.g. "DB connection (default)".
     */
    abstract protected function label(): string;

    /**
     * Override to short-circuit the probe with a "skipped (<reason>)" message. Default: always probe.
     *
     * Used by checkers whose underlying driver supports only a destructive or unavailable probe
     * in certain runtime configurations (APCu in CLI, NullAdapter, Messenger transports without
     * MessageCountAwareInterface, etc.).
     */
    protected function skipReason(): ?string
    {
        return null;
    }
}
