<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker;

use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\Context;

final class SuccessChecker implements CheckInterface
{
    public function isSupport(Context $context): bool
    {
        return true;
    }

    public function check(CheckResult $result, Context $context): CheckResult
    {
        $result->addMessage('success dump check');

        return $result;
    }
}
