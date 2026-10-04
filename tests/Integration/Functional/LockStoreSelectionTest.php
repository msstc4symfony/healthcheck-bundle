<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Integration\Functional;

use Closure;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\ActionInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Request as CheckRequest;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Response as CheckResponse;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Msstc4Symfony\HealthCheckBundle\Test\Integration\Kernel\TestKernel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;

/**
 * FrameworkBundle 8.1 predefines ".lock.flock.store" and ".lock.semaphore.store" for every app with
 * symfony/lock; only the store behind the configured `framework.lock` resource may be probed.
 */
final class LockStoreSelectionTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(LockFactory::class)) {
            self::markTestSkipped('symfony/lock is not installed');
        }

        new Filesystem()->remove(sys_get_temp_dir() . '/msstc4symfony-healthcheck-bundle-test');
    }

    /**
     * @return iterable<string, array{non-empty-string, list<non-empty-string>}>
     */
    public static function configuredStores(): iterable
    {
        yield 'flock' => ['flock', ['.lock.semaphore.store']];
        yield 'in-memory' => ['in-memory', ['.lock.semaphore.store', '.lock.flock.store']];
    }

    /**
     * @param non-empty-string $store
     * @param list<non-empty-string> $unusedStores
     */
    #[DataProvider('configuredStores')]
    public function testProbesOnlyTheConfiguredStore(string $store, array $unusedStores): void
    {
        $result = $this->runReadiness('lock_' . str_replace('-', '_', $store), static function (ContainerConfigurator $container) use ($store): void {
            $container->extension('framework', ['lock' => $store]);
        });

        $lockLines = $this->lockLines([...$result->messages, ...$result->errors, ...$result->warnings]);

        self::assertSame(['Lock store (lock.default) passed'], $lockLines);
        foreach ($unusedStores as $unused) {
            self::assertStringNotContainsString($unused . ')', $lockLines[0]);
        }
    }

    /**
     * A DSN store's service id ends in a hash of the DSN; the checker id is named after the resource,
     * so configuration keyed by it does not depend on the DSN.
     */
    public function testDsnStoreCheckerIdIsNamedAfterItsResource(): void
    {
        $result = $this->runReadiness('lock_dsn', static function (ContainerConfigurator $container): void {
            $container->extension('framework', ['lock' => ['default' => 'flock:///proc/msstc4symfony-unwritable', 'reports' => 'in-memory']]);
            $container->extension('msstc4symfony_healthcheck', [
                'non_critical' => ['healthcheck.checker.lock.default'],
                'timeouts' => ['overrides' => ['healthcheck.checker.lock.reports' => 1000]],
            ]);
        });

        self::assertTrue($result->success, implode(PHP_EOL, $result->errors));
        self::assertSame(['Lock store (lock.reports) passed'], $this->lockLines($result->messages));
        self::assertCount(1, $this->lockLines($result->warnings));
        self::assertStringStartsWith('Lock store (lock.default) failed (', $this->lockLines($result->warnings)[0]);
    }

    /**
     * @param non-empty-string $environment
     * @param Closure(ContainerConfigurator): void $configure
     */
    private function runReadiness(string $environment, Closure $configure): CheckResponse
    {
        $kernel = new TestKernel($environment, false, $configure);
        $kernel->boot();

        try {
            $testContainer = $kernel->getContainer()->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $testContainer);
            $action = $testContainer->get(ActionInterface::class);
            self::assertInstanceOf(ActionInterface::class, $action);

            return $action->run(new CheckRequest(CheckTypeEnum::READINESS));
        } finally {
            $kernel->shutdown();
        }
    }

    /**
     * @param array<string> $lines
     *
     * @return list<string>
     */
    private function lockLines(array $lines): array
    {
        return array_values(array_filter($lines, static fn (string $line): bool => str_starts_with($line, 'Lock store (')));
    }
}
