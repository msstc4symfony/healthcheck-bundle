<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Event;

use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * PSR-14 dispatcher decorator that:
 *  - tolerates a missing inner dispatcher (no-op),
 *  - swallows any listener exception so a buggy subscriber cannot fail the probe.
 *
 * Action and ParallelAction compose this instead of carrying their own copy of the
 * same fault-isolation policy.
 */
final readonly class SafeEventDispatcher
{
    public function __construct(
        private ?EventDispatcherInterface $inner = null,
    ) {
    }

    public function dispatch(object $event): void
    {
        if (!$this->inner instanceof EventDispatcherInterface) {
            return;
        }

        try {
            $this->inner->dispatch($event);
        } catch (Throwable) {
            // A buggy listener must never fail the readiness probe.
        }
    }
}
