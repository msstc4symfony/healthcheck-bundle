<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

use OpenSearch\Client;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class OpenSearchChecker extends AbstractReadinessChecker
{
    public function __construct(
        private Client $connection,
        private string $name,
    ) {
    }

    #[Override]
    protected function doCheck(): void
    {
        $this->connection->cluster()->health();
    }

    #[Override]
    protected function label(): string
    {
        return sprintf('OpenSearch (%s)', $this->name);
    }
}
