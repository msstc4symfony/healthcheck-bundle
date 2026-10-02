<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use RuntimeException;

final readonly class ThrowingChecker implements CheckInterface
{
    public function __construct(
        private string $message,
    ) {
    }

    public function isSupport(Context $context): bool
    {
        return true;
    }

    public function check(CheckResult $result, Context $context): CheckResult
    {
        throw new RuntimeException($this->message);
    }
}
