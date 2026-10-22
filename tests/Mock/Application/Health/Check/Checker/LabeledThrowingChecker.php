<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\LabeledCheckerInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Override;
use RuntimeException;

final readonly class LabeledThrowingChecker implements CheckInterface, LabeledCheckerInterface
{
    public const LABEL = 'Queue (main)';

    public function __construct(
        private string $message,
    ) {
    }

    #[Override]
    public function isSupport(Context $context): bool
    {
        return true;
    }

    #[Override]
    public function check(CheckResult $result, Context $context): CheckResult
    {
        throw new RuntimeException($this->message);
    }

    #[Override]
    public function checkerLabel(): string
    {
        return self::LABEL;
    }
}
