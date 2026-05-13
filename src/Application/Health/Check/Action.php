<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Request;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Response;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Event\HealthCheckerCompletedEvent;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Event\HealthCheckRunCompletedEvent;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Event\HealthCheckRunStartedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Throwable;

final readonly class Action implements ActionInterface
{
    /**
     * @param iterable<CheckInterface> $healthCheckers
     */
    public function __construct(
        #[AutowireIterator(CheckInterface::class)]
        private iterable $healthCheckers,
        private ?EventDispatcherInterface $eventDispatcher = null,
    ) {
    }

    public function run(Request $request): Response
    {
        $this->dispatch(new HealthCheckRunStartedEvent($request));

        $result = new CheckResult();
        $context = new Context($request->type, $request->options);
        $runStartedAt = microtime(true);

        foreach ($this->healthCheckers as $checker) {
            if (!$checker->isSupport($context)) {
                continue;
            }

            $errorsBefore = count($result->errors);
            $checkerStartedAt = microtime(true);

            $result = $checker->check($result, $context);

            $this->dispatch(new HealthCheckerCompletedEvent(
                $checker::class,
                (microtime(true) - $checkerStartedAt) * 1000,
                count($result->errors) === $errorsBefore,
            ));
        }

        $response = new Response($result->errors, $result->messages, $result->warnings);
        $this->dispatch(new HealthCheckRunCompletedEvent(
            $response,
            (microtime(true) - $runStartedAt) * 1000,
        ));

        return $response;
    }

    private function dispatch(object $event): void
    {
        if (!$this->eventDispatcher instanceof EventDispatcherInterface) {
            return;
        }
        try {
            $this->eventDispatcher->dispatch($event);
        } catch (Throwable) {
            // A buggy listener must never fail the readiness probe.
        }
    }
}
