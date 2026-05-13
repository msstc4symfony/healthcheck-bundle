<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\Enum;

enum CheckTypeEnum: string
{
    case READINESS = 'readiness';
    case LIVELINESS = 'liveliness';
}
