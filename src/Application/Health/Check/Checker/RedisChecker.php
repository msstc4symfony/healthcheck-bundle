<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker;

use Redis;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class RedisChecker extends AbstractReadinessChecker
{
    public function __construct(
        private Redis $connection,
    ) {
    }

    protected function doCheck(): void
    {
        if (!$this->connection->set(CheckInterface::PROBE_KEY, (string) time(), 1)) {
            throw new RuntimeException('SET command returned false');
        }
    }

    protected function label(): string
    {
        return 'Redis connection';
    }
}
