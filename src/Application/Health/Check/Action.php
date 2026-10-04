<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckerClass;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Request;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Response;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Event\HealthCheckerCompletedEvent;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Event\HealthCheckRunCompletedEvent;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Event\HealthCheckRunStartedEvent;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Event\SafeEventDispatcher;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class Action implements ActionInterface
{
    /**
     * @param iterable<CheckInterface> $healthCheckers
     */
    public function __construct(
        #[AutowireIterator(CheckInterface::class)]
        private iterable $healthCheckers,
        private SafeEventDispatcher $eventDispatcher,
    ) {
    }

    #[Override]
    public function run(Request $request): Response
    {
        $this->eventDispatcher->dispatch(new HealthCheckRunStartedEvent($request));

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

            $this->eventDispatcher->dispatch(new HealthCheckerCompletedEvent(
                CheckerClass::of($checker),
                (microtime(true) - $checkerStartedAt) * 1000,
                count($result->errors) === $errorsBefore,
            ));
        }

        $response = new Response($result->errors, $result->messages, $result->warnings);
        $this->eventDispatcher->dispatch(new HealthCheckRunCompletedEvent(
            $response,
            (microtime(true) - $runStartedAt) * 1000,
        ));

        return $response;
    }
}
