<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker;

use Closure;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\AbstractReadinessChecker;
use Override;

final readonly class SharedKeyCheckerFixture extends AbstractReadinessChecker
{
    /**
     * @param Closure(): null $probe
     * @param non-empty-string|null $key
     */
    public function __construct(private Closure $probe, private string $name, private ?string $key = 'redis:c')
    {
    }

    #[Override]
    protected function doCheck(): ?string
    {
        return ($this->probe)();
    }

    #[Override]
    protected function label(): string
    {
        return sprintf('Pool (%s)', $this->name);
    }

    #[Override]
    protected function sharedProbeKey(): ?string
    {
        return $this->key;
    }
}
