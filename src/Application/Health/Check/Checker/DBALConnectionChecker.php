<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

use Doctrine\DBAL\Connection;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class DBALConnectionChecker extends AbstractReadinessChecker
{
    public function __construct(
        private Connection $connection,
        private string $name,
    ) {
    }

    #[Override]
    protected function doCheck(): ?string
    {
        // Always round-trip: isConnected() stays true after the server dropped a long-lived connection.
        $this->connection->fetchOne($this->connection->getDatabasePlatform()->getDummySelectSQL());

        return null;
    }

    #[Override]
    protected function label(): string
    {
        return sprintf('DB connection (%s)', $this->name);
    }
}
