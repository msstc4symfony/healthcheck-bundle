<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Enum;

enum CheckTypeEnum: string
{
    case READINESS = 'readiness';
    case LIVELINESS = 'liveliness';
}
