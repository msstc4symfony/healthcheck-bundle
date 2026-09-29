<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Event;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Request;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class HealthCheckRunStartedEvent
{
    public function __construct(
        public Request $request,
    ) {
    }
}
