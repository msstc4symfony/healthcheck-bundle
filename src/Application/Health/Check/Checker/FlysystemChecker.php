<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker;

use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class FlysystemChecker extends AbstractReadinessChecker
{
    public function __construct(
        private FilesystemOperator $filesystem,
        private string $name,
    ) {
    }

    protected function doCheck(): void
    {
        // Lightweight read-only probe: existence check on the root path.
        // Throws FilesystemException on backend connection error; returns bool on success.
        $this->filesystem->directoryExists('/');
    }

    protected function label(): string
    {
        return sprintf('Flysystem (%s)', $this->name);
    }
}
