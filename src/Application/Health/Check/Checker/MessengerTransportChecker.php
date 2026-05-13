<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\SetupableTransportInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

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

            return;
        }

        if ($this->transport instanceof SetupableTransportInterface) {
            $this->transport->setup();
        }
    }

    protected function label(): string
    {
        return sprintf('Messenger transport (%s)', $this->name);
    }
}
