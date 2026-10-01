<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

use Override;
use RdKafka\KafkaConsumer;
use RdKafka\Producer;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class KafkaChecker extends AbstractReadinessChecker
{
    private const int METADATA_TIMEOUT_MS = 2000;

    public function __construct(
        private Producer|KafkaConsumer $connection,
        private string $name,
    ) {
    }

    #[Override]
    protected function doCheck(): void
    {
        // Fetches cluster metadata; raises RdKafka\Exception on broker connectivity issues.
        $this->connection->getMetadata(false, null, self::METADATA_TIMEOUT_MS);
    }

    #[Override]
    protected function label(): string
    {
        return sprintf('Kafka (%s)', $this->name);
    }
}
