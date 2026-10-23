<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Run;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Probe outcomes of one run keyed by backend, so checkers of several pools on one connection pay for it
 * once. A run builds its own Context, so nothing outlives it.
 *
 * Fibers are synchronous today: a checker finishes before the next starts. An outcome is recorded only
 * after its probe completed, first one wins (??=); with truly async fibers the worst case is two probes
 * of one key.
 *
 * @internal
 */
#[Exclude]
final class SharedProbes
{
    /** @var array<non-empty-string, SharedProbe> */
    private array $outcomes = [];

    /** @var non-negative-int|null */
    private ?int $reusedDurationMs = null;

    /**
     * @param non-empty-string $key
     */
    public function outcomeOf(string $key): ?SharedProbe
    {
        return $this->outcomes[$key] ?? null;
    }

    /**
     * An outcome another checker is about to report: the timeout decorator around that checker takes the
     * probe duration with takeReusedDurationMs().
     *
     * @param non-empty-string $key
     */
    public function reuse(string $key): ?SharedProbe
    {
        $outcome = $this->outcomeOf($key);
        if ($outcome instanceof SharedProbe) {
            $this->reusedDurationMs = $outcome->durationMs;
        }

        return $outcome;
    }

    /**
     * @return non-negative-int|null the duration of the probe reused since the last call, null when none was
     */
    public function takeReusedDurationMs(): ?int
    {
        $duration = $this->reusedDurationMs;
        $this->reusedDurationMs = null;

        return $duration;
    }

    /**
     * @param non-empty-string $key
     */
    public function record(string $key, SharedProbe $outcome): void
    {
        $this->outcomes[$key] ??= $outcome;
    }
}
