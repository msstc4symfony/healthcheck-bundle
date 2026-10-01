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
    protected function doCheck(): void
    {
        if (!$this->connection->isConnected()) {
            $this->connection->getServerVersion();
        }
    }

    #[Override]
    protected function label(): string
    {
        return sprintf('DB connection (%s)', $this->name);
    }
}
