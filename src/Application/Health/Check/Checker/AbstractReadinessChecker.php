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
        try {
            $skipReason = $this->skipReason();
            if ($skipReason !== null) {
                $result->addMessage(sprintf('%s skipped (%s)', $this->label(), $skipReason));

                return $result;
            }

            $detail = $this->doCheck();
            $result->addMessage(sprintf('%s passed', $this->label()) . ($detail !== null ? sprintf(' (%s)', CredentialRedactor::redact($detail)) : ''));
        } catch (Throwable $e) {
            $result->addError(sprintf('%s failed (%s)', $this->label(), CredentialRedactor::redact($e->getMessage())));
        }

        return $result;
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
}
