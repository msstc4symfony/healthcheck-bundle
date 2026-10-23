<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Run;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The wall-clock allowance of one probe run, shared by all its checkers. It never skips the checker that
 * would be the first of the run: only once one has finished is the budget enforced.
 *
 * @internal
 */
#[Exclude]
final class ProbeBudget
{
    private bool $anyFinished = false;

    /**
     * @param int $startedAtNs hrtime(true) at the start of the run
     * @param positive-int $limitMs
     */
    public function __construct(
        public readonly int $startedAtNs,
        public readonly int $limitMs,
    ) {
        if ($limitMs < 1) {
            throw new InvalidArgumentException(sprintf('The probe budget must be positive, got %d ms.', $limitMs));
        }
    }

    /**
     * @param int<1, max>|null $limitMs null leaves the run unlimited
     */
    public static function start(?int $limitMs, int $startedAtNs): ?self
    {
        return $limitMs === null ? null : new self($startedAtNs, $limitMs);
    }

    public function checkerFinished(): void
    {
        $this->anyFinished = true;
    }

    /**
     * @return non-empty-string|null the failure to report instead of running the checker, or null while it may run
     */
    public function exhaustedMessage(string $label, int $nowNs): ?string
    {
        $elapsedMs = intdiv($nowNs - $this->startedAtNs, 1_000_000);
        if (!$this->anyFinished || $elapsedMs <= $this->limitMs) {
            return null;
        }

        return sprintf('%s skipped: probe budget exhausted (%d ms > %d ms)', $label, $elapsedMs, $this->limitMs);
    }
}
