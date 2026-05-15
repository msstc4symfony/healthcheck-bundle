<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Presentation\Command;

use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'healthcheck:readiness', description: 'Check readiness status')]
final class HealthReadinessCommand extends AbstractHealthCommand
{
    protected function getType(): CheckTypeEnum
    {
        return CheckTypeEnum::READINESS;
    }
}
