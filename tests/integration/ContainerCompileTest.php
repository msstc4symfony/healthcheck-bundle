<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Integration;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Action;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\ActionInterface;
use Msstc4Symfony\HealthCheckBundle\HealthCheckBundle;
use Msstc4Symfony\HealthCheckBundle\Presentation\Command\HealthLivelinessCommand;
use Msstc4Symfony\HealthCheckBundle\Presentation\Command\HealthReadinessCommand;
use Msstc4Symfony\HealthCheckBundle\Presentation\Controller\HealthController;
use Msstc4Symfony\HealthCheckBundle\Test\Integration\Kernel\TestKernel;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RouterInterface;

/**
 * Boots a real Symfony kernel with FrameworkBundle + HealthCheckBundle and asserts
 * that container compilation, service wiring, route registration, and CLI command
 * discovery all work end-to-end. The pure-unit detector tests use a bare
 * ContainerBuilder and miss everything that depends on services.yaml PSR-4 loading.
 */
final class ContainerCompileTest extends KernelTestCase
{
    protected function setUp(): void
    {
        new Filesystem()->remove(sys_get_temp_dir() . '/msstc4symfony-healthcheck-bundle-test');
    }

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testKernelBootsAndRegistersBundle(): void
    {
        $kernel = self::bootKernel();

        self::assertArrayHasKey('HealthCheckBundle', $kernel->getBundles());
        self::assertInstanceOf(HealthCheckBundle::class, $kernel->getBundles()['HealthCheckBundle']);
    }

    public function testActionInterfaceResolvesToSequentialActionByDefault(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertTrue($container->has(ActionInterface::class));
        self::assertInstanceOf(Action::class, $container->get(ActionInterface::class));
    }

    public function testHealthControllerIsResolvable(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertTrue($container->has(HealthController::class));
        self::assertInstanceOf(HealthController::class, $container->get(HealthController::class));
    }

    public function testCommandsAreRegistered(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertTrue($container->has(HealthLivelinessCommand::class));
        self::assertTrue($container->has(HealthReadinessCommand::class));
    }

    public function testProbeRoutesAreRegistered(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get('router');

        self::assertInstanceOf(RouterInterface::class, $router);
        $routes = $router->getRouteCollection();

        self::assertNotNull($routes->get('healthcheck-ping'));
        self::assertSame('/_/healthcheck/ping', $routes->get('healthcheck-ping')->getPath());

        self::assertNotNull($routes->get('healthcheck-readiness'));
        self::assertSame('/_/healthcheck/readiness', $routes->get('healthcheck-readiness')->getPath());

        self::assertNotNull($routes->get('healthcheck-liveliness'));
        self::assertSame('/_/healthcheck/liveliness', $routes->get('healthcheck-liveliness')->getPath());
    }
}
