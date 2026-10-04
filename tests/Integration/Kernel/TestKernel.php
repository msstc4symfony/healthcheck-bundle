<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Integration\Kernel;

use Closure;
use Msstc4Symfony\HealthCheckBundle\HealthCheckBundle;
use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Throwable;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    private bool $bootPushedErrorHandler = false;

    private bool $bootPushedExceptionHandler = false;

    /**
     * @param (Closure(ContainerConfigurator): void)|null $configureServices extra services; give each variant its own environment so compiled containers do not collide
     */
    public function __construct(
        string $environment,
        bool $debug,
        private readonly ?Closure $configureServices = null,
    ) {
        parent::__construct($environment, $debug);
    }

    /**
     * FrameworkBundle registers its ErrorHandler on every boot unless symfony/runtime is installed;
     * PHPUnit flags the handlers left behind as risky.
     */
    #[Override]
    public function boot(): void
    {
        if ($this->booted) {
            parent::boot();

            return;
        }

        $errorHandler = $this->topErrorHandler();
        $exceptionHandler = $this->topExceptionHandler();
        parent::boot();
        $this->bootPushedErrorHandler = $this->topErrorHandler() !== $errorHandler;
        $this->bootPushedExceptionHandler = $this->topExceptionHandler() !== $exceptionHandler;
    }

    #[Override]
    public function shutdown(): void
    {
        parent::shutdown();

        if ($this->bootPushedErrorHandler) {
            restore_error_handler();
            $this->bootPushedErrorHandler = false;
        }

        if ($this->bootPushedExceptionHandler) {
            restore_exception_handler();
            $this->bootPushedExceptionHandler = false;
        }
    }

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new HealthCheckBundle(),
        ];
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/msstc4symfony-healthcheck-bundle-test/cache/' . $this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/msstc4symfony-healthcheck-bundle-test/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'http_method_override' => false,
            'test' => true,
            'router' => ['utf8' => true],
            // 'php_errors.log' defaults to true in 7+ and registers a global error handler
            // that survives kernel shutdown — trips PHPUnit's failOnRisky. Force off.
            'php_errors' => ['log' => false],
        ]);

        // Silence the default Symfony console logger; it writes to stdout/stderr and
        // trips beStrictAboutOutputDuringTests during functional tests.
        $container->services()
            ->set('logger', NullLogger::class)
            ->public()
        ;

        if ($this->configureServices instanceof Closure) {
            ($this->configureServices)($container);
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(
            \dirname(__DIR__, 3) . '/src/Presentation/Controller/',
            'attribute',
        );
    }

    /**
     * @return (callable(int, string, string, int): bool)|null
     */
    private function topErrorHandler(): ?callable
    {
        $handler = set_error_handler(null);
        restore_error_handler();

        return $handler;
    }

    /**
     * @return (callable(Throwable): void)|null
     */
    private function topExceptionHandler(): ?callable
    {
        $handler = set_exception_handler(null);
        restore_exception_handler();

        return $handler;
    }
}
