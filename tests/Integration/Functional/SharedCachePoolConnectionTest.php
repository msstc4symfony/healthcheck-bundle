<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Integration\Functional;

use Closure;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\ActionInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Request as CheckRequest;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Response as CheckResponse;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Msstc4Symfony\HealthCheckBundle\Test\Integration\Kernel\TestKernel;
use Override;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Every cache pool keeps its own checker. Pools on one Redis DSN share the connection service
 * CachePoolPass builds for it, so within a run only the first of them probes it; the others report
 * its outcome. Ports 1 and 2 refuse the connection at once.
 */
#[RequiresPhpExtension('redis')]
final class SharedCachePoolConnectionTest extends TestCase
{
    private const REDIS_ONE = 'redis://127.0.0.1:1';

    private const REDIS_TWO = 'redis://127.0.0.1:2';

    private const SHARED = 'shared connection probe via';

    // Components installed as runtime dependencies (as in CI) add pools of their own on cache.app.
    private const POOLS = ['cache.app', 'pool_a', 'pool_b', 'pool_c', 'pool_d', 'pool_e', 'pool_t', 'pool_local'];

    #[Override]
    protected function setUp(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir() . '/msstc4symfony-healthcheck-bundle-test');
    }

    public function testEveryPoolKeepsItsCheckerAndOnlyTheFirstOnASharedConnectionProbes(): void
    {
        $lines = $this->cacheLines($this->runReadiness('shared_cache_dsn', self::pools(...)));

        $pools = ['cache.app', 'pool_a', 'pool_b', 'pool_c', 'pool_d', 'pool_t', 'pool_local'];
        self::assertCount(count($pools), $lines);
        foreach ($pools as $pool) {
            self::assertCount(1, array_filter(array_keys($lines), static fn (string $name): bool => $name === $pool), $pool);
        }

        $probed = array_keys(array_filter($lines, static fn (string $line): bool => !str_contains($line, self::SHARED)));
        $shared = array_keys(array_filter($lines, static fn (string $line): bool => str_contains($line, self::SHARED)));

        // One plain probe per DSN, one tag-aware probe, and the lone third DSN. A tags pool is tagged as
        // its inner plain adapter, so it shares like any other plain pool on its connection.
        self::assertCount(4, $probed, implode(', ', $probed));
        self::assertCount(3, $shared, implode(', ', $shared));
        self::assertContains('pool_b', $probed, 'the tag-aware pool is never covered by a plain pool');
        self::assertContains('pool_local', $probed);
        self::assertCount(1, array_intersect(['cache.app', 'pool_a', 'pool_t'], $probed));
        self::assertCount(1, array_intersect(['pool_c', 'pool_d'], $probed));
        foreach ($shared as $pool) {
            self::assertMatchesRegularExpression('/: shared connection probe via Cache \([^)]+\) connection failed: /', $lines[$pool]);
        }
    }

    public function testPoolsWhoseProviderIsTheDefaultProviderAliasShareItsConnection(): void
    {
        $lines = $this->cacheLines($this->runReadiness('shared_cache_alias', static function (ContainerConfigurator $container): void {
            self::pools($container);
            $container->extension('framework', ['cache' => ['pools' => ['pool_e' => ['adapter' => 'cache.adapter.redis', 'provider' => 'cache.default_redis_provider']]]]);
        }));

        self::assertArrayHasKey('pool_e', $lines);
        $conn1 = array_intersect_key($lines, array_flip(['cache.app', 'pool_a', 'pool_e']));
        self::assertCount(3, $conn1);
        self::assertCount(1, array_filter($conn1, static fn (string $line): bool => !str_contains($line, self::SHARED)));
        self::assertCount(2, array_filter($conn1, static fn (string $line): bool => str_contains($line, self::SHARED)));
    }

    public function testPoolsListedInTheConfigurationStillExistAndAreReportedSeparately(): void
    {
        $result = $this->runReadiness('shared_cache_non_critical', static function (ContainerConfigurator $container): void {
            self::pools($container);
            $container->extension('msstc4symfony_healthcheck', [
                'non_critical' => ['healthcheck.checker.cache.pool.pool_c', 'healthcheck.checker.cache.pool.pool_d'],
                'timeouts' => ['overrides' => ['healthcheck.checker.cache.pool.pool_a' => 1500]],
            ]);
        });

        self::assertCount(2, $this->cacheLines(new CheckResponse([], [], $result->warnings)));
        self::assertCount(5, $this->cacheLines(new CheckResponse($result->errors, [], [])));
    }

    private static function pools(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['cache' => [
            'app' => 'cache.adapter.redis',
            'default_redis_provider' => self::REDIS_ONE,
            'pools' => [
                'pool_a' => ['adapter' => 'cache.adapter.redis', 'provider' => self::REDIS_ONE],
                'pool_b' => ['adapter' => 'cache.adapter.redis_tag_aware', 'provider' => self::REDIS_ONE],
                'pool_c' => ['adapter' => 'cache.adapter.redis', 'provider' => self::REDIS_TWO],
                'pool_d' => ['adapter' => 'cache.adapter.redis', 'provider' => self::REDIS_TWO],
                'pool_t' => ['adapter' => 'cache.adapter.redis', 'provider' => self::REDIS_ONE, 'tags' => true],
                'pool_local' => ['adapter' => 'cache.adapter.redis', 'provider' => 'redis://127.0.0.1:3'],
                'pool_files' => ['adapter' => 'cache.adapter.filesystem'],
                'pool_array' => ['adapter' => 'cache.adapter.array'],
            ],
        ]]);
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
     * @return array<string, string> pool name => its report line
     */
    private function cacheLines(CheckResponse $result): array
    {
        $lines = [];
        foreach ([...$result->messages, ...$result->errors, ...$result->warnings] as $line) {
            if (preg_match('/^Cache \((?:\S+ : )?([^)]+)\) connection/', $line, $match) === 1 && in_array($match[1], self::POOLS, true)) {
                $lines[$match[1]] = $line;
            }
        }

        return $lines;
    }
}
