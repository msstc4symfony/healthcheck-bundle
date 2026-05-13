<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker;

use ClickHouseDB\Client;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class ClickHouseChecker extends AbstractReadinessChecker
{
    public function __construct(
        private Client $connection,
        private string $name,
    ) {
    }

    protected function doCheck(): void
    {
        if (!$this->connection->ping()) {
            throw new RuntimeException('ping returned false');
        }
    }

    protected function label(): string
    {
        return sprintf('ClickHouse (%s)', $this->name);
    }
}
