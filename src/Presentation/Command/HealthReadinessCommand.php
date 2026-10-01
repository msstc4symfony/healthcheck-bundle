<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Presentation\Command;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'healthcheck:readiness', description: 'Check readiness status')]
final class HealthReadinessCommand extends AbstractHealthCommand
{
    #[Override]
    protected function getType(): CheckTypeEnum
    {
        return CheckTypeEnum::READINESS;
    }
}
