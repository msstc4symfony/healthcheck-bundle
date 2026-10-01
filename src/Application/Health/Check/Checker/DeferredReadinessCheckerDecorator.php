<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

use Closure;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
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
 * @internal wired by HealthCheckerDeferredConstructionPass
 */
#[Exclude]
final readonly class DeferredReadinessCheckerDecorator implements CheckInterface
{
    /**
     * @param Closure(): CheckInterface $factory
     * @param non-empty-string $name checker service id, reported when construction fails
     */
    public function __construct(
        private Closure $factory,
        private string $name,
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
        try {
            $inner = ($this->factory)();
        } catch (Throwable $e) {
            $result->addError(sprintf('%s failed (%s)', $this->name, $e->getMessage()));

            return $result;
        }

        return $inner->check($result, $context);
    }
}
