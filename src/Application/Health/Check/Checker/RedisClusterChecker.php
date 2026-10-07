<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

use Override;
use RedisCluster;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class RedisClusterChecker extends AbstractReadinessChecker
{
    public function __construct(
        private RedisCluster $connection,
    ) {
    }

    #[Override]
    protected function doCheck(): ?string
    {
        $reply = $this->connection->set(CheckInterface::PROBE_KEY, (string) time(), ['EX' => 1]);

        if ($reply !== true) {
            throw new RuntimeException(sprintf('SET command returned %s', $reply === false ? 'false' : get_debug_type($reply)));
        }

        return null;
    }

    #[Override]
    protected function label(): string
    {
        return 'Redis cluster connection';
    }
}
