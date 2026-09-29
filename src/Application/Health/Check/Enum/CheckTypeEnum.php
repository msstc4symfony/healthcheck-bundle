<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum;

enum CheckTypeEnum: string
{
    case READINESS = 'readiness';
    case LIVELINESS = 'liveliness';
}
