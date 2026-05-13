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
 * Fiber-based ActionInterface that runs supported checkers in parallel fibers.
 *
 * Note: wall-clock parallelism is only realized when checkers use async-aware I/O
 * (amphp/http-client, react/mysql, etc.). For synchronous probes (PDO, Predis, sync HTTP)
 * the fibers run to completion sequentially and the speedup is negligible — but the API
 * shape is correct for future migration to async drivers.
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
            while (!$entry['fiber']->isTerminated()) {
                usleep(1000);
            }

            try {
                /** @var CheckResult $partial */
                $partial = $entry['fiber']->getReturn();
                $errorsBefore = count($partial->errors);
                foreach ($partial->messages as $message) {
                    $combined->addMessage($message);
                }
                foreach ($partial->errors as $error) {
                    $combined->addError($error);
                }
                foreach ($partial->warnings as $warning) {
                    $combined->addWarning($warning);
                }
                $success = $errorsBefore === 0;
            } catch (Throwable $e) {
                $combined->addError(sprintf('%s failed (%s)', $entry['checker']::class, $e->getMessage()));
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
        $this->eventDispatcher?->dispatch($event);
    }
}
