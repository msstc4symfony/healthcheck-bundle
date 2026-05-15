<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker;

use PhpAmqpLib\Connection\AbstractConnection;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class RabbitmqChecker extends AbstractReadinessChecker
{
    public function __construct(
        private AbstractConnection $connection,
    ) {
    }

    protected function doCheck(): void
    {
        $this->connection->reconnect();

        if (!$this->connection->isConnected()) {
            throw new RuntimeException('not connected after reconnect');
        }
    }

    protected function label(): string
    {
        return 'RabbitMQ connection';
    }
}
