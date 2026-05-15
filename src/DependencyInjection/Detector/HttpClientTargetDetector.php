<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\DependencyInjection\Detector;

use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\HttpClientChecker;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\HttpProbeTarget;
use MSSTC4PHP\HealthCheckBundle\DependencyInjection\HealthCheckExtension;
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

            $target = new Definition(HttpProbeTarget::class)
                ->setArgument(0, $cfg['url'])
                ->setArgument(1, $cfg['method'])
                ->setArgument(2, $cfg['expected_status_codes'])
                ->setArgument(3, $cfg['timeout_seconds'])
            ;

            yield sprintf('healthcheck.checker.http_client.%s', $name) => new Definition(HttpClientChecker::class)
                ->addArgument($clientRef)
                ->addArgument($name)
                ->addArgument($target)
            ;
        }
    }
}
