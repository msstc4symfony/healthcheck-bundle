<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

use Override;
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

    #[Override]
    protected function doCheck(): ?string
    {
        $this->connection->reconnect();

        if (!$this->connection->isConnected()) {
            throw new RuntimeException('not connected after reconnect');
        }

        return null;
    }

    #[Override]
    protected function label(): string
    {
        return 'RabbitMQ connection';
    }
}
