<?php declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Request;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Response;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class Action implements ActionInterface
{
    /**
     * @param iterable<CheckInterface> $healthCheckers
     */
    public function __construct(
        #[AutowireIterator(CheckInterface::class)]
        private readonly iterable $healthCheckers,
    ) {
    }

    public function run(Request $request): Response
    {
        $result = new CheckResult();
        $context = new Context($request->type, $request->options);

        foreach ($this->healthCheckers as $checker) {
            if ($checker->isSupport($context)) {
                $result = $checker->check($result, $context);
            }
        }

        return new Response(count($result->errors) === 0, $result->errors, $result->messages);
    }
}
