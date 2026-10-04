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
 * Same isolation as ReadinessDependencyIsolationTest for a checker declared as a child of an
 * abstract template: the child carries no class until ResolveChildDefinitionsPass.
 */
final class ChildDefinitionReadinessIsolationTest extends TestCase
{
    private TestKernel $kernel;

    #[Override]
    protected function setUp(): void
    {
        new Filesystem()->remove(sys_get_temp_dir() . '/msstc4symfony-healthcheck-bundle-test');

        $this->kernel = new TestKernel('child_definition_readiness', false, static function (ContainerConfigurator $container): void {
            $services = $container->services();
            $services->set(UnconstructibleDependencyFixture::class);
            $services->set('app.checker.template', DependentReadinessCheckerFixture::class)->abstract()->autowire();
            $services->set('app.checker.dependent')->parent('app.checker.template')->tag(CheckInterface::class);
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

    public function testReadinessReportsUnconstructibleDependencyUnderTheCheckerLabel(): void
    {
        $response = $this->kernel->handle(Request::create('/_/healthcheck/readiness'));

        $content = (string) $response->getContent();
        self::assertSame(Response::HTTP_NOT_ACCEPTABLE, $response->getStatusCode(), $content);
        self::assertStringContainsString(
            sprintf('Dependent failed (%s)', UnconstructibleDependencyFixture::FAILURE),
            $content,
        );
    }
}
