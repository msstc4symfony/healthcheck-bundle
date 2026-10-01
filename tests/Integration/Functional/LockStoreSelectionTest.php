<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Integration\Functional;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\ActionInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Request as CheckRequest;
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
        $kernel = new TestKernel('lock_' . str_replace('-', '_', $store), false, static function (ContainerConfigurator $container) use ($store): void {
            $container->extension('framework', ['lock' => $store]);
        });
        $kernel->boot();

        try {
            $testContainer = $kernel->getContainer()->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $testContainer);
            $action = $testContainer->get(ActionInterface::class);
            self::assertInstanceOf(ActionInterface::class, $action);

            $result = $action->run(new CheckRequest(CheckTypeEnum::READINESS));
        } finally {
            $kernel->shutdown();
        }

        $lockLines = array_values(array_filter(
            [...$result->messages, ...$result->errors, ...$result->warnings],
            static fn (string $line): bool => str_starts_with($line, 'Lock store ('),
        ));

        self::assertCount(1, $lockLines, implode(PHP_EOL, $lockLines));
        self::assertStringEndsWith(' passed', $lockLines[0]);
        foreach ($unusedStores as $unused) {
            self::assertStringNotContainsString($unused . ')', $lockLines[0]);
        }
    }
}
