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
    protected function doCheck(): ?string
    {
        $this->connection->cluster()->health();

        return null;
    }

    #[Override]
    protected function label(): string
    {
        return sprintf('OpenSearch (%s)', $this->name);
    }
}
