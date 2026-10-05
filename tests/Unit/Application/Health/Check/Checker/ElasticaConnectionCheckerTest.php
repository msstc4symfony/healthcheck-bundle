<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use Elastic\Transport\Transport;
use Elastica\Client;
use Elastica\Cluster;
use Elastica\Cluster\Health;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\ElasticaConnectionChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ElasticaConnectionCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Client::class)) {
            self::markTestSkipped('ruflin/elastica is not installed');
        }
    }

    public function testIsSupportReadinessOnly(): void
    {
        $checker = new ElasticaConnectionChecker(self::createStub(Client::class), 'main');

        self::assertTrue($checker->isSupport(new Context(CheckTypeEnum::READINESS)));
        self::assertFalse($checker->isSupport(new Context(CheckTypeEnum::LIVELINESS)));
    }

    public function testCheckSkipsWhenNoConnectionsConfigured(): void
    {
        $client = self::createStub(Client::class);
        $client->method('getConfigValue')->willReturnCallback(static fn (string $key, mixed $default = null): mixed => $default);

        $result = new ElasticaConnectionChecker($client, 'main')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame([], $result->errors);
        self::assertSame(['Elastica connection (main) skipped (connections list is empty)'], $result->messages);
    }

    public function testCheckSkipsWhenOnlyLocalhostConfigured(): void
    {
        $client = self::createStub(Client::class);
        $client->method('getConfigValue')->willReturnCallback(static fn (string $key): mixed => $key === 'connections' ? [['host' => 'localhost']] : null);

        $result = new ElasticaConnectionChecker($client, 'main')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame([], $result->errors);
        self::assertSame(['Elastica connection (main) skipped (connections list is empty)'], $result->messages);
    }

    public function testCheckProbesAClusterNamedLocalhostAmongOthers(): void
    {
        $health = self::createStub(Health::class);
        $health->method('getStatus')->willReturn('yellow');

        $cluster = self::createStub(Cluster::class);
        $cluster->method('getHealth')->willReturn($health);

        $client = self::createStub(Client::class);
        $client->method('getConfigValue')->willReturnCallback(static fn (string $key): mixed => $key === 'connections' ? [['host' => 'localhost'], ['host' => 'es-prod']] : null);
        $client->method('getCluster')->willReturn($cluster);

        $result = new ElasticaConnectionChecker($client, 'main')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Elastica connection (main) passed (cluster status: yellow)'], $result->messages);
    }

    public function testCheckPassesWithRealCluster(): void
    {
        $health = self::createStub(Health::class);
        $health->method('getStatus')->willReturn('green');

        $cluster = self::createStub(Cluster::class);
        $cluster->method('getHealth')->willReturn($health);

        $client = self::createStub(Client::class);
        $client->method('getConfigValue')->willReturnCallback(static fn (string $key): mixed => $key === 'connections' ? [['host' => 'es-prod']] : null);
        $client->method('getCluster')->willReturn($cluster);

        $result = new ElasticaConnectionChecker($client, 'main')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame([], $result->errors);
        self::assertSame(['Elastica connection (main) passed (cluster status: green)'], $result->messages);
    }

    public function testCheckOnException(): void
    {
        $client = self::createStub(Client::class);
        $client->method('getConfigValue')->willThrowException(new RuntimeException('boom'));

        $result = new ElasticaConnectionChecker($client, 'main')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame([], $result->messages);
        self::assertSame(['Elastica connection (main) failed (boom)'], $result->errors);
    }

    public function testCheckRedactsCredentialsInFailureReason(): void
    {
        $client = self::createStub(Client::class);
        $client->method('getConfigValue')->willThrowException(new RuntimeException('Unreachable "https://elastic:s3cret@es:9200"'));

        $result = new ElasticaConnectionChecker($client, 'main')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Elastica connection (main) failed (Unreachable "https://***@es:9200")'], $result->errors);
    }

    public function testDefaultConfigurationIsSkipped(): void
    {
        $result = new ElasticaConnectionChecker(new Client(), 'main')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Elastica connection (main) skipped (connections list is empty)'], $result->messages);
    }

    public function testConfiguredHostIsProbed(): void
    {
        $config = $this->isElasticaEight()
            ? ['hosts' => ['http://es.invalid:9200'], 'retries' => 0]
            : ['host' => 'es.invalid', 'port' => 9200];

        $result = new ElasticaConnectionChecker(new Client($config), 'main')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertStringStartsWith('Elastica connection (main) failed (', $result->errors[0] ?? '');
    }

    public function testCloudIdIsNotTreatedAsUnconfigured(): void
    {
        if (!$this->isElasticaEight()) {
            self::markTestSkipped('cloud_id is an Elastica 8 setting');
        }

        $client = new Client(['cloud_id' => 'test:' . base64_encode('es.invalid$abc$def'), 'retries' => 0]);
        $result = new ElasticaConnectionChecker($client, 'main')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame([], $result->messages);
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('provideSkipRuleCases')]
    public function testSkipRule(array $config, bool $skipped): void
    {
        $client = self::createStub(Client::class);
        $client->method('getConfigValue')->willReturnCallback(
            static fn (string $key, mixed $default = null): mixed => $config[$key] ?? $default,
        );

        $result = new ElasticaConnectionChecker($client, 'main')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame($skipped, $result->messages === ['Elastica connection (main) skipped (connections list is empty)']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, bool}>
     */
    public static function provideSkipRuleCases(): iterable
    {
        yield 'elastica 7 top-level url' => [['connections' => [], 'url' => 'http://es:9200'], false];
        yield 'elastica 7 top-level host' => [['connections' => [], 'host' => 'es'], false];
        yield 'elastica 7 servers' => [['connections' => [], 'servers' => [['host' => 'es']]], false];
        yield 'elastica 8 default host' => [['hosts' => ['localhost:9200']], true];
        yield 'elastica 8 explicit localhost url' => [['hosts' => ['http://localhost:9200']], false];
        yield 'elastica 8 empty hosts' => [['hosts' => []], true];
        yield 'elastica 8 default host with cloud id' => [['hosts' => ['localhost:9200'], 'cloud_id' => 'x'], false];
        yield 'elastica 8 empty hosts with cloud id' => [['hosts' => [], 'cloud_id' => 'x'], false];
    }

    private function isElasticaEight(): bool
    {
        return class_exists(Transport::class);
    }
}
