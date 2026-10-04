<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

use Doctrine\Migrations\DependencyFactory;
use Override;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class DoctrineMigrationsChecker extends AbstractReadinessChecker
{
    public function __construct(
        private DependencyFactory $dependencyFactory,
    ) {
    }

    #[Override]
    protected function doCheck(): ?string
    {
        $newMigrations = $this->dependencyFactory
            ->getMigrationStatusCalculator()
            ->getNewMigrations()
        ;

        $count = count($newMigrations);
        if ($count > 0) {
            throw new RuntimeException(sprintf('%d unapplied migration(s)', $count));
        }

        return null;
    }

    #[Override]
    protected function label(): string
    {
        return 'Doctrine migrations';
    }
}
