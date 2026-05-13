<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker;

use OpenSearch\Client;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class OpenSearchChecker extends AbstractReadinessChecker
{
    public function __construct(
        private Client $connection,
        private string $name,
    ) {
    }

    protected function doCheck(): void
    {
        /** @psalm-suppress UndefinedClass */
        $this->connection->cluster()->health();
    }

    protected function label(): string
    {
        return sprintf('OpenSearch (%s)', $this->name);
    }
}
