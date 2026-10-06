<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

use Elastica\Client;
use Override;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class ElasticaConnectionChecker extends AbstractReadinessChecker
{
    private const string ELASTICA8_DEFAULT_HOST = 'localhost:9200';

    public function __construct(
        private Client $connection,
        private string $name,
    ) {
    }

    #[Override]
    protected function skipReason(): ?string
    {
        return $this->isUnconfigured() ? 'connections list is empty' : null;
    }

    #[Override]
    protected function doCheck(): string
    {
        $status = $this->connection->getCluster()->getHealth()->getStatus();
        if ($status === 'red') {
            throw new RuntimeException('cluster status: red');
        }

        return 'cluster status: ' . $status;
    }

    #[Override]
    protected function label(): string
    {
        return sprintf('Elastica connection (%s)', $this->name);
    }

    private function isUnconfigured(): bool
    {
        $hosts = $this->connection->getConfigValue('hosts');
        if (is_array($hosts)) {
            return $this->connection->getConfigValue('cloud_id') === null
                && ($hosts === [] || $hosts === [self::ELASTICA8_DEFAULT_HOST]);
        }

        $connections = $this->connection->getConfigValue('connections', []);
        if (!is_array($connections) || $connections === []) {
            return $this->connection->getConfigValue('host') === null
                && $this->connection->getConfigValue('url') === null
                && $this->connection->getConfigValue('servers') === null;
        }

        return count($connections) === 1 && is_array($connections[0]) && ($connections[0]['host'] ?? null) === 'localhost';
    }
}
