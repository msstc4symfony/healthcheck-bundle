<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Integration\Functional;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CacheChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Test\Integration\Kernel\TestKernel;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Readiness probes cache pools backed by infrastructure only, not FrameworkBundle's local system
 * pools (cache.system, cache.validator, cache.serializer, …).
 */
final class CachePoolSelectionTest extends TestCase
{
    private const array SYSTEM_POOLS = ['cache.system', 'cache.validator', 'cache.serializer', 'cache.property_info'];

    protected function setUp(): void
    {
        new Filesystem()->remove(sys_get_temp_dir() . '/msstc4symfony-healthcheck-bundle-test');
    }

    public function testLocalPoolsAreNotProbed(): void
    {
        $content = $this->readiness(new TestKernel('cache_local', false), Response::HTTP_OK);

        self::assertStringNotContainsString('Cache (', $content);
    }

    public function testAppPoolOnRedisIsProbedWithoutTheSystemPools(): void
    {
        if (!extension_loaded('redis') && !class_exists(Client::class)) {
            self::markTestSkipped('neither ext-redis nor predis/predis is installed');
        }

        // Port 1 refuses the connection at once: the pool is probed, and fails, without a Redis server.
        $kernel = new TestKernel('cache_redis', false, static function (ContainerConfigurator $container): void {
            $container->extension('framework', ['cache' => [
                'app' => 'cache.adapter.redis',
                'default_redis_provider' => 'redis://127.0.0.1:1',
            ]]);
        });

        $content = $this->readiness($kernel, Response::HTTP_NOT_ACCEPTABLE);

        self::assertMatchesRegularExpression('~Cache \([^)]*cache\.app\) connection failed~', $content);
        foreach (self::SYSTEM_POOLS as $pool) {
            self::assertStringNotContainsString($pool . ')', $content);
        }
    }

    public function testLocalPoolCanBeProbedByRegisteringCacheCheckerExplicitly(): void
    {
        $kernel = new TestKernel('cache_explicit', false, static function (ContainerConfigurator $container): void {
            $container->services()
                ->set('app.cache_app_checker', CacheChecker::class)
                ->args([service('cache.app'), 'cache.app'])
                ->tag(CheckInterface::class)
            ;
        });

        $content = $this->readiness($kernel, Response::HTTP_OK);

        self::assertStringContainsString('Cache (cache.app) connection passed', $content);
    }

    private function readiness(TestKernel $kernel, int $expectedStatus): string
    {
        $kernel->boot();
        try {
            $response = $kernel->handle(Request::create('/_/healthcheck/readiness'));
        } finally {
            $kernel->shutdown();
        }

        $content = (string) $response->getContent();
        self::assertSame($expectedStatus, $response->getStatusCode(), $content);

        return $content;
    }
}
