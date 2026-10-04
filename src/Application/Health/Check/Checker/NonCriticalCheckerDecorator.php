<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\CredentialRedactor;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Throwable;

/**
 * Demotes errors produced by the inner checker, and any exception it throws, into warnings.
 * Used for non-critical dependencies whose unavailability should not fail readiness
 * (caches, analytics-only stores, etc.).
 *
 * On demotion, any partial messages/warnings added by the inner are discarded (symmetric
 * with TimeoutCheckerDecorator's "override-stripping" policy) so we don't simultaneously
 * report "checker A passed (message)" and "checker A failed (warning)" for the same run.
 */
#[Exclude]
final readonly class NonCriticalCheckerDecorator implements CheckInterface, CheckerDecoratorInterface
{
    public function __construct(
        private CheckInterface $inner,
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
        $messagesBefore = count($result->messages);
        $errorsBefore = count($result->errors);
        $warningsBefore = count($result->warnings);

        try {
            $result = $this->inner->check($result, $context);
        } catch (Throwable $e) {
            $result->resetTrailing($messagesBefore, $errorsBefore, $warningsBefore);
            $result->addWarning(sprintf(
                '%s failed (%s)',
                CheckerClass::of($this->inner),
                CredentialRedactor::redact($e->getMessage()),
            ));

            return $result;
        }

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

    #[Override]
    public function decoratedCheckerClass(): string
    {
        return CheckerClass::of($this->inner);
    }
}
