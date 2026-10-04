<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit;

use LogicException;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Action;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\ActionInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\CachedActionDecorator;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckerClass;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\RedisChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\ParallelAction;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\ContainerIds;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\CheckerDetectorInterface;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\LockStoreDetector;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\HealthCheckerAutoDetectionPass;
use Msstc4Symfony\HealthCheckBundle\HealthCheckBundle;
use Msstc4Symfony\HealthCheckBundle\Presentation\Controller\HealthController;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\SuccessChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\RegisterAutoconfigureAttributesPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveInstanceofConditionalsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

final class HealthCheckBundleTest extends TestCase
{
    public function testExtensionAliasIsTheConfigurationRoot(): void
    {
        $extension = new HealthCheckBundle()->getContainerExtension();

        self::assertNotNull($extension);
        self::assertSame('msstc4symfony_healthcheck', $extension->getAlias());
        self::assertSame(ContainerIds::ALIAS, $extension->getAlias());
    }

    public function testDefaultConfigurationSetsTheContainerParameters(): void
    {
        $container = $this->load([]);

        self::assertSame([], $container->getParameter('msstc4symfony_healthcheck.http_client_targets'));
        self::assertSame(2000, $container->getParameter('msstc4symfony_healthcheck.default_timeout_ms'));
        self::assertSame([], $container->getParameter('msstc4symfony_healthcheck.timeout_overrides'));
        self::assertSame([], $container->getParameter('msstc4symfony_healthcheck.non_critical_checkers'));
    }

    public function testConfiguredValuesReachTheContainerParameters(): void
    {
        $container = $this->load([[
            'non_critical' => ['healthcheck.checker.app.redis'],
            'timeouts' => ['default_ms' => 1500, 'overrides' => ['healthcheck.checker.app.redis' => 500]],
            'http_client' => ['billing' => ['url' => 'https://billing.example/health']],
        ]]);

        self::assertSame(['healthcheck.checker.app.redis'], $container->getParameter(ContainerIds::PARAM_NON_CRITICAL_CHECKERS));
        self::assertSame(1500, $container->getParameter(ContainerIds::PARAM_DEFAULT_TIMEOUT_MS));
        self::assertSame(['healthcheck.checker.app.redis' => 500], $container->getParameter(ContainerIds::PARAM_TIMEOUT_OVERRIDES));
        self::assertSame(
            ['billing' => [
                'url' => 'https://billing.example/health',
                'method' => 'GET',
                'expected_status_codes' => [200, 204],
                'client' => null,
                'timeout_seconds' => 3,
            ]],
            $container->getParameter(ContainerIds::PARAM_HTTP_CLIENT_TARGETS),
        );
    }

    public function testAcceptsTheLowerBounds(): void
    {
        $container = $this->load([[
            'execution' => 'sequential',
            'cache' => ['ttl_seconds' => 1],
            'timeouts' => ['default_ms' => 1, 'overrides' => ['healthcheck.checker.app.redis' => 1]],
            'http_client' => ['billing' => ['url' => 'http://billing.example/health', 'timeout_seconds' => 1]],
        ]]);

        self::assertSame(1, $container->getParameter(ContainerIds::PARAM_DEFAULT_TIMEOUT_MS));
        self::assertSame(Action::class, (string) $container->getAlias(ActionInterface::class));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function belowLowerBound(): iterable
    {
        yield 'cache ttl' => [['cache' => ['ttl_seconds' => 0]]];
        yield 'default timeout' => [['timeouts' => ['default_ms' => 0]]];
        yield 'timeout override' => [['timeouts' => ['overrides' => ['healthcheck.checker.app.redis' => 0]]]];
        yield 'http probe timeout' => [['http_client' => ['billing' => ['url' => 'https://billing.example', 'timeout_seconds' => 0]]]];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('belowLowerBound')]
    public function testRejectsValuesBelowTheLowerBounds(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([$config]);
    }

    public function testRejectsAnHttpProbeWithoutAnHttpScheme(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('http_client.url must use http:// or https:// scheme');

        $this->load([['http_client' => ['billing' => ['url' => 'ftp://billing.example']]]]);
    }

    public function testRejectsACachePoolThatIsNotAServiceId(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "msstc4symfony_healthcheck.cache.pool" option must be a service id.');

        $this->load([['cache' => ['enabled' => true, 'pool' => 5]]]);
    }

    public function testLoadExtensionRejectsAConfigurationSectionThatIsNotAnArray(): void
    {
        $builder = new ContainerBuilder();
        $instanceof = [];
        $file = (string) new ReflectionClass(HealthCheckBundle::class)->getFileName();
        $configurator = new ContainerConfigurator($builder, new PhpFileLoader($builder, new FileLocator()), $instanceof, $file, $file);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "msstc4symfony_healthcheck.timeouts" configuration is not an array.');

        new HealthCheckBundle()->loadExtension(
            ['execution' => 'sequential', 'cache' => ['enabled' => false], 'non_critical' => [], 'timeouts' => 'malformed', 'http_client' => []],
            $configurator,
            $builder,
        );
    }

    public function testRegistersServicesButNotCheckersOrWiring(): void
    {
        $container = $this->load([]);

        self::assertTrue($this->isService($container, Action::class));
        self::assertTrue($this->isService($container, HealthController::class));
        self::assertTrue($this->isService($container, LockStoreDetector::class));
        self::assertFalse($this->isService($container, RedisChecker::class), 'checkers are created by detectors');
        self::assertFalse($this->isService($container, CheckerClass::class));
        self::assertFalse($this->isService($container, HealthCheckerAutoDetectionPass::class));
        self::assertFalse($this->isService($container, HealthCheckBundle::class));
    }

    public function testAutoconfigurationTagsDetectorsAndApplicationCheckers(): void
    {
        $container = $this->load([]);
        $container->setDefinition('app.checker', new Definition(SuccessChecker::class)->setAutoconfigured(true));

        new RegisterAutoconfigureAttributesPass()->process($container);
        new ResolveInstanceofConditionalsPass()->process($container);

        self::assertTrue($container->getDefinition(LockStoreDetector::class)->hasTag(CheckerDetectorInterface::TAG));
        self::assertTrue($container->getDefinition('app.checker')->hasTag(CheckInterface::class));
    }

    public function testDefaultAliasPointsAtAction(): void
    {
        $container = $this->load([]);

        self::assertSame(Action::class, (string) $container->getAlias(ActionInterface::class));
    }

    public function testParallelExecutionRegistersAndAliasesParallelAction(): void
    {
        $container = $this->load([['execution' => 'parallel']]);

        self::assertSame(ParallelAction::class, $container->getDefinition(ContainerIds::SERVICE_PARALLEL_ACTION)->getClass());
        self::assertTrue($container->getDefinition(ContainerIds::SERVICE_PARALLEL_ACTION)->isAutowired());
        self::assertSame(ContainerIds::SERVICE_PARALLEL_ACTION, (string) $container->getAlias(ActionInterface::class));
        self::assertFalse($container->hasDefinition(Action::class), 'the unused sequential runner is dropped');
    }

    public function testCacheEnabledAliasesCachedDecoratorOverInner(): void
    {
        $container = $this->load([['cache' => ['enabled' => true, 'pool' => 'cache.health', 'ttl_seconds' => 7]]]);

        $cached = $container->getDefinition(ContainerIds::SERVICE_CACHED_ACTION);
        self::assertSame(CachedActionDecorator::class, $cached->getClass());
        self::assertEquals(new Reference(Action::class), $cached->getArgument(0));
        self::assertEquals(new Reference('cache.health'), $cached->getArgument(1));
        self::assertSame(7, $cached->getArgument(2));
        self::assertSame(ContainerIds::SERVICE_CACHED_ACTION, (string) $container->getAlias(ActionInterface::class));
    }

    public function testCacheEnabledWithParallelStacksDecoratorsCorrectly(): void
    {
        $container = $this->load([['execution' => 'parallel', 'cache' => ['enabled' => true]]]);

        $cached = $container->getDefinition(ContainerIds::SERVICE_CACHED_ACTION);
        self::assertEquals(new Reference(ContainerIds::SERVICE_PARALLEL_ACTION), $cached->getArgument(0));
        self::assertEquals(new Reference('cache.app'), $cached->getArgument(1));
        self::assertSame(5, $cached->getArgument(2));
        self::assertSame(ContainerIds::SERVICE_CACHED_ACTION, (string) $container->getAlias(ActionInterface::class));
    }

    private function isService(ContainerBuilder $container, string $id): bool
    {
        return $container->hasDefinition($id) && !$container->getDefinition($id)->hasTag('container.excluded');
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        // Symfony 7.4.0's extension loader reads it; every kernel defines it.
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());

        $extension = new HealthCheckBundle()->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load($configs, $container);

        return $container;
    }
}
