<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\DependencyInjection\Detector;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\HttpClientChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\HttpProbeTarget;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\ContainerIds;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\HttpClientTargetDetector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HttpClientTargetDetectorTest extends TestCase
{
    public function testDetectYieldsCheckerForConfiguredTargetWithDefaultClient(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(ContainerIds::PARAM_HTTP_CLIENT_TARGETS, [
            'upstream' => [
                'url' => 'https://api.example.com/health',
                'method' => 'GET',
                'expected_status_codes' => [200, 204],
                'client' => null,
                'timeout_seconds' => 3,
            ],
        ]);

        $detected = iterator_to_array(new HttpClientTargetDetector()->detect($container));

        $checker = $detected['healthcheck.checker.http_client.upstream'];
        self::assertSame(HttpClientChecker::class, $checker->getClass());
        self::assertEquals(new Reference(HttpClientInterface::class), $checker->getArgument(0));
        self::assertSame('upstream', $checker->getArgument(1));

        $target = $checker->getArgument(2);
        self::assertInstanceOf(Definition::class, $target);
        self::assertSame(HttpProbeTarget::class, $target->getClass());
        self::assertSame('https://api.example.com/health', $target->getArgument(0));
        self::assertSame('GET', $target->getArgument(1));
        self::assertSame([200, 204], $target->getArgument(2));
        self::assertSame(3, $target->getArgument(3));
    }

    public function testDetectUsesExplicitlyConfiguredClient(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(ContainerIds::PARAM_HTTP_CLIENT_TARGETS, [
            'upstream' => [
                'url' => 'https://internal/health',
                'method' => 'HEAD',
                'expected_status_codes' => [200],
                'client' => 'app.custom_http_client',
                'timeout_seconds' => 1,
            ],
        ]);

        $detected = iterator_to_array(new HttpClientTargetDetector()->detect($container));

        $checker = $detected['healthcheck.checker.http_client.upstream'];
        self::assertEquals(new Reference('app.custom_http_client'), $checker->getArgument(0));
    }

    public function testDetectReturnsEmptyWhenParameterMissing(): void
    {
        self::assertSame([], iterator_to_array(new HttpClientTargetDetector()->detect(new ContainerBuilder())));
    }

    public function testDetectReturnsEmptyWhenParameterEmpty(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(ContainerIds::PARAM_HTTP_CLIENT_TARGETS, []);

        self::assertSame([], iterator_to_array(new HttpClientTargetDetector()->detect($container)));
    }
}
