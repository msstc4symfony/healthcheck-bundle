<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Test\Unit\DependencyInjection\Detector;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\HttpClientChecker;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\HttpClientTargetDetector;
use MaxShamaev\HealthCheckBundle\DependencyInjection\HealthCheckExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HttpClientTargetDetectorTest extends TestCase
{
    public function testDetectYieldsCheckerForConfiguredTargetWithDefaultClient(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(HealthCheckExtension::PARAM_HTTP_CLIENT_TARGETS, [
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
        self::assertSame('https://api.example.com/health', $checker->getArgument(2));
        self::assertSame('GET', $checker->getArgument(3));
        self::assertSame([200, 204], $checker->getArgument(4));
        self::assertSame(3, $checker->getArgument(5));
    }

    public function testDetectUsesExplicitlyConfiguredClient(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(HealthCheckExtension::PARAM_HTTP_CLIENT_TARGETS, [
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
        $container->setParameter(HealthCheckExtension::PARAM_HTTP_CLIENT_TARGETS, []);

        self::assertSame([], iterator_to_array(new HttpClientTargetDetector()->detect($container)));
    }
}
