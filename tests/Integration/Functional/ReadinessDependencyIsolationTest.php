<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Integration\Functional;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Test\Integration\Kernel\TestKernel;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\DependentReadinessCheckerFixture;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\UnconstructibleDependencyFixture;
use Override;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A readiness dependency that cannot even be constructed (SemaphoreStore without ext-sysvsem) must
 * fail only its own readiness check; liveliness never touches readiness dependencies.
 */
final class ReadinessDependencyIsolationTest extends TestCase
{
    private TestKernel $kernel;

    #[Override]
    protected function setUp(): void
    {
        new Filesystem()->remove(sys_get_temp_dir() . '/msstc4symfony-healthcheck-bundle-test');

        $this->kernel = new TestKernel('broken_readiness_dependency', false, static function (ContainerConfigurator $container): void {
            $services = $container->services();
            $services->set(UnconstructibleDependencyFixture::class);
            $services->set(DependentReadinessCheckerFixture::class)->autowire()->tag(CheckInterface::class);
        });
        $this->kernel->boot();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    public function testLivelinessIsUpDespiteUnconstructibleReadinessDependency(): void
    {
        $response = $this->kernel->handle(Request::create('/_/healthcheck/liveliness'));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringContainsString('Result: up', (string) $response->getContent());
    }

    public function testReadinessReportsUnconstructibleDependencyAsFailedCheck(): void
    {
        $response = $this->kernel->handle(Request::create('/_/healthcheck/readiness'));

        $content = (string) $response->getContent();
        self::assertSame(Response::HTTP_NOT_ACCEPTABLE, $response->getStatusCode(), $content);
        self::assertStringContainsString(
            sprintf('%s failed (%s)', DependentReadinessCheckerFixture::class, UnconstructibleDependencyFixture::FAILURE),
            $content,
        );
    }
}
