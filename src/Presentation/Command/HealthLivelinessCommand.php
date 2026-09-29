<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Presentation\Command;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'healthcheck:liveliness', description: 'Check liveliness status', aliases: ['healthcheck'])]
final class HealthLivelinessCommand extends AbstractHealthCommand
{
    protected function getType(): CheckTypeEnum
    {
        return CheckTypeEnum::LIVELINESS;
    }
}
