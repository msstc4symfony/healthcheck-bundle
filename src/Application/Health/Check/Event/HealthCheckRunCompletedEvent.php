<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Event;

use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\Response;
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
