<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

use Memcached;
use Override;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class MemcachedChecker extends AbstractReadinessChecker
{
    public function __construct(
        private Memcached $connection,
    ) {
    }

    #[Override]
    protected function doCheck(): ?string
    {
        if (!$this->connection->set(CheckInterface::PROBE_KEY, time(), 1)) {
            throw new RuntimeException('SET command returned false');
        }

        return null;
    }

    #[Override]
    protected function label(): string
    {
        return 'Memcached connection';
    }
}
