<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Run;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * What the first checker of a shared backend found, and how long it took.
 *
 * @internal
 */
#[Exclude]
final readonly class SharedProbe
{
    /**
     * @param non-empty-string $checkerLabel the checker that really probed
     * @param non-empty-string|null $error credential-redacted failure, null when the probe passed
     * @param non-negative-int $durationMs
     */
    public function __construct(
        public string $checkerLabel,
        public ?string $error,
        public int $durationMs,
    ) {
    }

    public static function passed(string $checkerLabel, int $startedAtNs, int $nowNs): self
    {
        return new self(self::label($checkerLabel), null, self::elapsedMs($startedAtNs, $nowNs));
    }

    public static function failed(string $checkerLabel, string $message, int $startedAtNs, int $nowNs): self
    {
        return new self(self::label($checkerLabel), $message !== '' ? $message : 'no message', self::elapsedMs($startedAtNs, $nowNs));
    }

    /**
     * Reports the outcome for a checker that did not probe. A probe slower than the reusing checker's own
     * budget reports `exceeded budget` even if it failed, as TimeoutCheckerDecorator does.
     *
     * @param positive-int|null $timeoutMs
     */
    public function reportTo(CheckResult $result, string $label, ?int $timeoutMs): void
    {
        if ($timeoutMs !== null && $this->durationMs > $timeoutMs) {
            $result->addError(sprintf('%s exceeded budget (%d ms > %d ms)', $label, $this->durationMs, $timeoutMs));
        } elseif ($this->error !== null) {
            $result->addError(sprintf(
                '%s: shared connection probe via %s failed: %s',
                $label,
                $this->checkerLabel,
                $this->error,
            ));
        } else {
            $result->addMessage(sprintf('%s passed (shared connection probed via %s)', $label, $this->checkerLabel));
        }
    }

    /**
     * @return non-empty-string
     */
    private static function label(string $label): string
    {
        return $label !== '' ? $label : 'checker';
    }

    /**
     * @param int $startedAtNs hrtime(true) when the probe began
     *
     * @return non-negative-int
     */
    private static function elapsedMs(int $startedAtNs, int $nowNs): int
    {
        return max(0, intdiv($nowNs - $startedAtNs, 1_000_000));
    }
}
