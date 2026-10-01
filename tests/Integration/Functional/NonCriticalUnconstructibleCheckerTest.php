<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Integration\Functional;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\ActionInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Request as CheckRequest;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\HealthCheckExtension;
use Msstc4Symfony\HealthCheckBundle\Test\Integration\Kernel\TestKernel;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\DependentReadinessCheckerFixture;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\UnconstructibleDependencyFixture;
use Override;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pins the decorator order NonCritical( Deferred( Timeout( inner ) ) ): a non-critical checker that
 * cannot be constructed degrades readiness to a warning instead of failing it.
 */
final class NonCriticalUnconstructibleCheckerTest extends TestCase
{
    private TestKernel $kernel;

    #[Override]
    protected function setUp(): void
    {
        new Filesystem()->remove(sys_get_temp_dir() . '/msstc4symfony-healthcheck-bundle-test');

        $this->kernel = new TestKernel('non_critical_unconstructible', false, static function (ContainerConfigurator $container): void {
            $container->extension(HealthCheckExtension::ALIAS, ['non_critical' => [DependentReadinessCheckerFixture::class]]);
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

    public function testReadinessStaysUpWithAWarning(): void
    {
        $response = $this->kernel->handle(Request::create('/_/healthcheck/readiness'));
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $testContainer = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $testContainer);
        $action = $testContainer->get(ActionInterface::class);
        self::assertInstanceOf(ActionInterface::class, $action);

        $result = $action->run(new CheckRequest(CheckTypeEnum::READINESS));

        self::assertTrue($result->success);
        self::assertSame([sprintf('Dependent failed (%s)', UnconstructibleDependencyFixture::FAILURE)], $result->warnings);
    }
}
