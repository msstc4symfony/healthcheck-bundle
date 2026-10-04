<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

use Elastica\Client;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class ElasticaConnectionChecker extends AbstractReadinessChecker
{
    public function __construct(
        private Client $connection,
        private string $name,
    ) {
    }

    #[Override]
    protected function skipReason(): ?string
    {
        $connections = $this->connection->getConfig('connections');

        $unconfigured = count($connections) === 0
            || (count($connections) === 1 && is_array($connections[0]) && ($connections[0]['host'] ?? null) === 'localhost');

        return $unconfigured ? 'connections list is empty' : null;
    }

    #[Override]
    protected function doCheck(): string
    {
        return 'cluster status: ' . $this->connection->getCluster()->getHealth()->getStatus();
    }

    #[Override]
    protected function label(): string
    {
        return sprintf('Elastica connection (%s)', $this->name);
    }
}
