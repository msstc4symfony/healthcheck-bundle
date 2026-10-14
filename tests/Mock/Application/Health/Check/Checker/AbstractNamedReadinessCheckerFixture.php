<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\AbstractReadinessChecker;
use Override;

/**
 * Declares the constructor and the private and protected promoted properties its subclasses inherit.
 */
abstract readonly class AbstractNamedReadinessCheckerFixture extends AbstractReadinessChecker
{
    public function __construct(
        private UnconstructibleDependencyFixture $dependency,
        private string $name,
        protected string $kind,
    ) {
    }

    #[Override]
    protected function doCheck(): ?string
    {
        return $this->dependency->ping() === 'pong' ? null : 'no pong';
    }

    #[Override]
    protected function label(): string
    {
        return sprintf('Named (%s, %s)', $this->name, $this->kind);
    }
}
