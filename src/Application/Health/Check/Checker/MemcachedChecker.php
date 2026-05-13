<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker;

use Memcached;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class MemcachedChecker extends AbstractReadinessChecker
{
    public function __construct(
        private Memcached $connection,
    ) {
    }

    protected function doCheck(): void
    {
        if (!$this->connection->set(CheckInterface::PROBE_KEY, time(), 1)) {
            throw new RuntimeException('SET command returned false');
        }
    }

    protected function label(): string
    {
        return 'Memcached connection';
    }
}
