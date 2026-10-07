<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

use Override;
use Predis\Client;
use Predis\Response\ErrorInterface;
use Predis\Response\ResponseInterface;
use Predis\Response\Status;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * A keyed write: a replication / sentinel client sends it to the master, a cluster client to the
 * node owning the key.
 */
#[Exclude]
final readonly class PredisChecker extends AbstractReadinessChecker
{
    public function __construct(
        private Client $connection,
    ) {
    }

    #[Override]
    protected function doCheck(): ?string
    {
        $reply = $this->connection->set(CheckInterface::PROBE_KEY, (string) time(), 'EX', 1);

        if (!$reply instanceof Status || $reply->getPayload() !== 'OK') {
            throw new RuntimeException(sprintf('SET command returned %s', $this->describe($reply)));
        }

        return null;
    }

    #[Override]
    protected function label(): string
    {
        return 'Redis connection';
    }

    /**
     * An error reply arrives as a value instead of a ServerException when the client runs with
     * the "exceptions" option off.
     */
    private function describe(?ResponseInterface $reply): string
    {
        return match (true) {
            $reply instanceof Status => $reply->getPayload(),
            $reply instanceof ErrorInterface => $reply->getMessage(),
            default => get_debug_type($reply),
        };
    }
}
