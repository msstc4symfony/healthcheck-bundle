<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Demotes errors produced by the inner checker into warnings. Used for non-critical
 * dependencies whose unavailability should not fail readiness (caches, analytics-only
 * stores, etc.).
 */
#[Exclude]
final readonly class NonCriticalCheckerDecorator implements CheckInterface
{
    public function __construct(
        private CheckInterface $inner,
    ) {
    }

    public function isSupport(Context $context): bool
    {
        return $this->inner->isSupport($context);
    }

    public function check(CheckResult $result, Context $context): CheckResult
    {
        $errorsBefore = count($result->errors);

        $result = $this->inner->check($result, $context);

        $errorsAfter = count($result->errors);
        if ($errorsAfter === $errorsBefore) {
            return $result;
        }

        // Move newly-added errors into warnings.
        $newErrors = array_slice($result->errors, $errorsBefore);
        $result->resetTrailing(
            count($result->messages),
            $errorsBefore,
            count($result->warnings),
        );
        foreach ($newErrors as $error) {
            $result->addWarning($error);
        }

        return $result;
    }
}
