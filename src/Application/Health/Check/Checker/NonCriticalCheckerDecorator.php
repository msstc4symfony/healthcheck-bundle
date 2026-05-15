<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker;

use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Demotes errors produced by the inner checker into warnings. Used for non-critical
 * dependencies whose unavailability should not fail readiness (caches, analytics-only
 * stores, etc.).
 *
 * On demotion, any partial messages/warnings added by the inner are discarded (symmetric
 * with TimeoutCheckerDecorator's "override-stripping" policy) so we don't simultaneously
 * report "checker A passed (message)" and "checker A failed (warning)" for the same run.
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
        $messagesBefore = count($result->messages);
        $errorsBefore = count($result->errors);
        $warningsBefore = count($result->warnings);

        $result = $this->inner->check($result, $context);

        if (count($result->errors) === $errorsBefore) {
            return $result;
        }

        $demoted = array_slice($result->errors, $errorsBefore);
        $result->resetTrailing($messagesBefore, $errorsBefore, $warningsBefore);
        foreach ($demoted as $error) {
            $result->addWarning($error);
        }

        return $result;
    }
}
