<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Application\Health\Check;

use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\Request;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\Response;

interface ActionInterface
{
    public function run(Request $request): Response;
}
