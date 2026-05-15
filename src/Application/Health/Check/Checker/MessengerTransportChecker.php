<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Probes a Symfony Messenger transport.
 *
 * Only transports implementing MessageCountAwareInterface get an active probe (read-only count).
 * For transports without that capability we report "skipped (no safe probe)" via the parent
 * template — reporting "passed" without an actual probe would be misleading.
 *
 * We deliberately do NOT fall back to setup() — that is destructive on several transports
 * (Doctrine creates tables, AMQP declares exchanges/queues) and unsafe to run on every poll.
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
        assert($this->transport instanceof MessageCountAwareInterface, 'skipReason() guards this');
        $this->transport->getMessageCount();
    }

    protected function label(): string
    {
        return sprintf('Messenger transport (%s)', $this->name);
    }

    protected function skipReason(): ?string
    {
        return $this->transport instanceof MessageCountAwareInterface
            ? null
            : 'transport does not support a safe probe';
    }
}
