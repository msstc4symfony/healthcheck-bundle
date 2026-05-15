<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Event;

use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Dispatched after each individual checker has run. Exposes wall-clock duration and
 * whether the checker added an error during this invocation.
 */
#[Exclude]
final readonly class HealthCheckerCompletedEvent
{
    /**
     * @param class-string<CheckInterface> $checkerClass
     */
    public function __construct(
        public string $checkerClass,
        public float $durationMs,
        public bool $success,
    ) {
    }
}
