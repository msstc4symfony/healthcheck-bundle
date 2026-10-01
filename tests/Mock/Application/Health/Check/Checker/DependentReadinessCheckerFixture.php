<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\AbstractReadinessChecker;
use Override;
use RuntimeException;

final readonly class DependentReadinessCheckerFixture extends AbstractReadinessChecker
{
    public function __construct(
        private UnconstructibleDependencyFixture $dependency,
    ) {
    }

    #[Override]
    protected function doCheck(): void
    {
        if ($this->dependency->ping() !== 'pong') {
            throw new RuntimeException('dependency did not answer');
        }
    }

    #[Override]
    protected function label(): string
    {
        return 'Dependent';
    }
}
