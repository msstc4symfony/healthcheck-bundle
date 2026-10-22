<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Event;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Response;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class HealthCheckRunCompletedEvent
{
    /**
     * @param CheckTypeEnum|null $type the probe; null only when the event is built by hand
     */
    public function __construct(
        public Response $response,
        public float $totalDurationMs,
        public ?CheckTypeEnum $type = null,
    ) {
    }
}
