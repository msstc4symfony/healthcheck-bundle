<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

use MongoDB\Client;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class MongoConnectionChecker extends AbstractReadinessChecker
{
    public function __construct(
        private Client $connection,
        private string $name,
    ) {
    }

    #[Override]
    protected function doCheck(): ?string
    {
        $this->connection->listDatabaseNames();

        return null;
    }

    #[Override]
    protected function label(): string
    {
        return sprintf('Mongo connection (%s)', $this->name);
    }
}
