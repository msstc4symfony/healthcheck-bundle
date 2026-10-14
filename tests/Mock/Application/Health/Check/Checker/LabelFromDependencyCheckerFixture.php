<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\AbstractReadinessChecker;
use Override;

/**
 * Its label() needs the dependency, which the deferred construction pass never builds.
 */
final readonly class LabelFromDependencyCheckerFixture extends AbstractReadinessChecker
{
    public function __construct(
        private UnconstructibleDependencyFixture $dependency,
        private string $name,
    ) {
    }

    #[Override]
    protected function doCheck(): ?string
    {
        return null;
    }

    #[Override]
    protected function label(): string
    {
        return sprintf('%s (%s)', $this->name, $this->dependency->ping());
    }
}
