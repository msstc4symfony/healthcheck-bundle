<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Event;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Dispatched after each individual checker has run. Exposes wall-clock duration and
 * whether the checker added an error ($success) or a warning ($warned) during this invocation.
 */
#[Exclude]
final readonly class HealthCheckerCompletedEvent
{
    /**
     * @param class-string<CheckInterface> $checkerClass
     * @param non-empty-string|null $checkerId the checker's service id; null for checkers an Action got as a list
     * @param CheckTypeEnum|null $type the probe; null only when the event is built by hand
     * @param bool $warned whether the checker added a warning, as a non-critical checker that failed does
     */
    public function __construct(
        public string $checkerClass,
        public float $durationMs,
        public bool $success,
        public ?string $checkerId = null,
        public ?CheckTypeEnum $type = null,
        public bool $warned = false,
    ) {
    }
}
