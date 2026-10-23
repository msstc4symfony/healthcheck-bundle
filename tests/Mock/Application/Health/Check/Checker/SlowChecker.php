<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\AbstractReadinessChecker;
use Override;

final readonly class SlowChecker extends AbstractReadinessChecker
{
    /**
     * @param non-negative-int $sleepMs
     */
    public function __construct(private int $sleepMs, private string $name = 'slow')
    {
    }

    #[Override]
    protected function doCheck(): ?string
    {
        usleep($this->sleepMs * 1000);

        return null;
    }

    #[Override]
    protected function label(): string
    {
        return sprintf('Slow (%s)', $this->name);
    }
}
