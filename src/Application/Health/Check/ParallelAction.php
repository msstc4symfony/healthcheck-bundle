<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check;

use Fiber;
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
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Throwable;

/**
 * Fiber-based ActionInterface that runs supported checkers in independent fibers.
 *
 * Honest limitation: PHP's Fibers are cooperative coroutines without a built-in scheduler.
 * For a synchronous checker (PDO/Predis/sync HTTP) Fiber::start() runs the closure to
 * completion before returning, so no wall-clock overlap occurs — this class is sequential
 * in practice today. The wiring is in place so a future migration to async-aware drivers
 * (amphp, react) yields real parallelism without touching call sites.
 */
#[Exclude]
final readonly class ParallelAction implements ActionInterface
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

        $context = new Context($request->type, $request->options);
        $runStartedAt = microtime(true);

        /** @var list<array{checker: CheckInterface, fiber: Fiber<void, void, CheckResult, void>, startedAt: float}> $entries */
        $entries = [];

        foreach ($this->healthCheckers as $checker) {
            if (!$checker->isSupport($context)) {
                continue;
            }

            $fiber = new Fiber(static fn (): CheckResult => $checker->check(new CheckResult(), $context));
            $fiber->start();

            $entries[] = ['checker' => $checker, 'fiber' => $fiber, 'startedAt' => microtime(true)];
        }

        $combined = new CheckResult();
        foreach ($entries as $entry) {
            // Synchronous fibers terminate on start(); no scheduling needed. When async-aware
            // checkers land, replace this drain with an actual loop (Suspension/Revolt).
            try {
                /** @var CheckResult $partial */
                $partial = $entry['fiber']->getReturn();
                foreach ($partial->messages as $message) {
                    $combined->addMessage($message);
                }
                foreach ($partial->errors as $error) {
                    $combined->addError($error);
                }
                foreach ($partial->warnings as $warning) {
                    $combined->addWarning($warning);
                }
                $success = $partial->errors === [];
            } catch (Throwable $e) {
                $combined->addError(sprintf('parallel: %s failed (%s)', $entry['checker']::class, $e->getMessage()));
                $success = false;
            }

            $this->dispatch(new HealthCheckerCompletedEvent(
                $entry['checker']::class,
                (microtime(true) - $entry['startedAt']) * 1000,
                $success,
            ));
        }

        $response = new Response($combined->errors, $combined->messages, $combined->warnings);
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
