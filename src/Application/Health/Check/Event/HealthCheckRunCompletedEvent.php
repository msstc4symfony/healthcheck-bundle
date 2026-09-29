<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Event;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Response;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class HealthCheckRunCompletedEvent
{
    public function __construct(
        public Response $response,
        public float $totalDurationMs,
    ) {
    }
}
