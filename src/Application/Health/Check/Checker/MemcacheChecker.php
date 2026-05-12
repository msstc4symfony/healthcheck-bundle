<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker;

use Memcache;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class MemcacheChecker extends AbstractReadinessChecker
{
    private const string CACHE_SERVICE_CELL = '__healthcheck';

    public function __construct(
        private Memcache $connection,
    ) {
    }

    protected function doCheck(): void
    {
        if (!$this->connection->set(self::CACHE_SERVICE_CELL, time(), 0, 1)) {
            throw new RuntimeException('SET command returned false');
        }
    }

    protected function label(): string
    {
        return 'Memcache connection';
    }
}
