<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class FlysystemChecker extends AbstractReadinessChecker
{
    private const string PROBE_PATH = '.healthcheck-probe';

    public function __construct(
        private FilesystemOperator $filesystem,
        private string $name,
    ) {
    }

    protected function doCheck(): void
    {
        // fileExists() exercises auth + reachability against backends like S3 even when the file
        // is missing. directoryExists('/') is unreliable on bucket-based stores (no notion of root).
        // The bool result is intentionally ignored; only exceptions from the backend signal failure.
        $this->filesystem->fileExists(self::PROBE_PATH);
    }

    protected function label(): string
    {
        return sprintf('Flysystem (%s)', $this->name);
    }
}
