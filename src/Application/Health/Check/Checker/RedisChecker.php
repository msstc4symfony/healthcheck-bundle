<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

use Override;
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

    #[Override]
    protected function doCheck(): void
    {
        if (!$this->connection->set(CheckInterface::PROBE_KEY, (string) time(), 1)) {
            throw new RuntimeException('SET command returned false');
        }
    }

    #[Override]
    protected function label(): string
    {
        return 'Redis connection';
    }
}
