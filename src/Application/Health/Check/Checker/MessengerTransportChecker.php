<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Probes a Symfony Messenger transport.
 *
 * Only transports implementing MessageCountAwareInterface get an active probe (read-only count).
 * For other transports we deliberately skip the probe — calling setup() would be destructive
 * (Doctrine creates tables, AMQP declares exchanges/queues, etc.), which is unsafe to run on
 * every readiness check.
 */
#[Exclude]
final readonly class MessengerTransportChecker extends AbstractReadinessChecker
{
    public function __construct(
        private TransportInterface $transport,
        private string $name,
    ) {
    }

    protected function doCheck(): void
    {
        if ($this->transport instanceof MessageCountAwareInterface) {
            $this->transport->getMessageCount();
        }
        // Else: no safe probe; service-graph instantiation already verified the transport.
    }

    protected function label(): string
    {
        return sprintf('Messenger transport (%s)', $this->name);
    }
}
