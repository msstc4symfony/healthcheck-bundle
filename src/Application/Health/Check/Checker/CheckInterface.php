<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker;

use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(CheckInterface::class)]
interface CheckInterface
{
    public const string PROBE_KEY = '__healthcheck';

    public function isSupport(Context $context): bool;

    public function check(CheckResult $result, Context $context): CheckResult;
}
