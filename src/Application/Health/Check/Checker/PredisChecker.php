<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

use Override;
use Predis\Client;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class PredisChecker extends AbstractReadinessChecker
{
    public function __construct(
        private Client $connection,
    ) {
    }

    #[Override]
    protected function doCheck(): void
    {
        if (!$this->connection->isConnected()) {
            $this->connection->connect();
        }

        if (!$this->connection->isConnected()) {
            throw new RuntimeException('not connected after reconnect');
        }
    }

    #[Override]
    protected function label(): string
    {
        return 'Redis connection';
    }
}
