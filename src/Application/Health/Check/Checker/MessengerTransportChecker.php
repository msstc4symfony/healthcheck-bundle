<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Throwable;

/**
 * Probes a Symfony Messenger transport.
 *
 * Only transports implementing MessageCountAwareInterface get an active probe (read-only count).
 * For transports without that capability we explicitly emit a "skipped" message instead of a
 * "passed" message — there was no active probe, so reporting success would be misleading.
 *
 * We deliberately do NOT fall back to setup() — that is destructive on several transports
 * (Doctrine creates tables, AMQP declares exchanges/queues) and unsafe to run on every poll.
 */
#[Exclude]
final readonly class MessengerTransportChecker implements CheckInterface
{
    public function __construct(
        private TransportInterface $transport,
        private string $name,
    ) {
    }

    public function isSupport(Context $context): bool
    {
        return $context->type === CheckTypeEnum::READINESS;
    }

    public function check(CheckResult $result, Context $context): CheckResult
    {
        $label = sprintf('Messenger transport (%s)', $this->name);

        if (!$this->transport instanceof MessageCountAwareInterface) {
            $result->addMessage(sprintf('%s skipped (transport does not support a safe probe)', $label));

            return $result;
        }

        try {
            $this->transport->getMessageCount();
            $result->addMessage(sprintf('%s passed', $label));
        } catch (Throwable $e) {
            $result->addError(sprintf('%s failed (%s)', $label, $e->getMessage()));
        }

        return $result;
    }
}
