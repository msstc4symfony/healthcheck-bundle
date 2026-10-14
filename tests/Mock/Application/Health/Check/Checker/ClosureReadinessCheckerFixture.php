<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker;

use Closure;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\AbstractReadinessChecker;
use Override;

/**
 * A readiness checker driven by closures. Named, not anonymous: PHP 8.2 has no readonly anonymous
 * classes, and only a readonly class may extend AbstractReadinessChecker.
 */
final readonly class ClosureReadinessCheckerFixture extends AbstractReadinessChecker
{
    /**
     * @param Closure(): ?string $probe
     * @param (Closure(): ?string)|null $skip
     */
    public function __construct(private Closure $probe, private ?Closure $skip)
    {
    }

    #[Override]
    protected function doCheck(): ?string
    {
        return ($this->probe)();
    }

    #[Override]
    protected function skipReason(): ?string
    {
        return $this->skip instanceof Closure ? ($this->skip)() : null;
    }

    #[Override]
    protected function label(): string
    {
        return 'Probe (test)';
    }
}
