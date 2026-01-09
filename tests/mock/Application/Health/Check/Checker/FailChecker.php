<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Context;

final class FailChecker implements CheckInterface
{
    public function isSupport(Context $context): bool
    {
        return true;
    }

    public function check(CheckResult $result, Context $context): CheckResult
    {
        $result->addError('fail dump check');

        return $result;
    }
}
