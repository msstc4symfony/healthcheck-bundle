<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\DependencyInjection\Detector;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\HttpClientChecker;
use MaxShamaev\HealthCheckBundle\DependencyInjection\HealthCheckExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpClientTargetDetector implements CheckerDetectorInterface
{
    /**
     * @return iterable<string, Definition>
     */
    public function detect(ContainerBuilder $container): iterable
    {
        if (!$container->hasParameter(HealthCheckExtension::PARAM_HTTP_CLIENT_TARGETS)) {
            return;
        }

        /** @var array<string, array{url: string, method: string, expected_status_codes: list<int>, client: string|null, timeout_seconds: int}> $targets */
        $targets = $container->getParameter(HealthCheckExtension::PARAM_HTTP_CLIENT_TARGETS);

        foreach ($targets as $name => $cfg) {
            $clientRef = $cfg['client'] !== null
                ? new Reference($cfg['client'])
                : new Reference(HttpClientInterface::class);

            yield sprintf('healthcheck.checker.http_client.%s', $name) => new Definition(HttpClientChecker::class)
                ->addArgument($clientRef)
                ->addArgument($name)
                ->addArgument($cfg['url'])
                ->addArgument($cfg['method'])
                ->addArgument($cfg['expected_status_codes'])
                ->addArgument($cfg['timeout_seconds'])
            ;
        }
    }
}
