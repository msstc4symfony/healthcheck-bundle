<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use Elastica\Client;
use Elastica\Cluster;
use Elastica\Cluster\Health;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\ElasticaConnectionChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
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
        $client->method('getConfig')->willReturn([]);

        $result = new ElasticaConnectionChecker($client, 'main')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame([], $result->errors);
        self::assertCount(1, $result->messages);
        self::assertStringContainsString('cannot be checked', $result->messages[0]);
    }

    public function testCheckSkipsWhenOnlyLocalhostConfigured(): void
    {
        $client = self::createStub(Client::class);
        $client->method('getConfig')->willReturn([['host' => 'localhost']]);

        $result = new ElasticaConnectionChecker($client, 'main')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame([], $result->errors);
        self::assertCount(1, $result->messages);
        self::assertStringContainsString('cannot be checked', $result->messages[0]);
    }

    public function testCheckPassesWithRealCluster(): void
    {
        $health = self::createStub(Health::class);
        $health->method('getStatus')->willReturn('green');

        $cluster = self::createStub(Cluster::class);
        $cluster->method('getHealth')->willReturn($health);

        $client = self::createStub(Client::class);
        $client->method('getConfig')->willReturn([['host' => 'es-prod']]);
        $client->method('getCluster')->willReturn($cluster);

        $result = new ElasticaConnectionChecker($client, 'main')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame([], $result->errors);
        self::assertCount(1, $result->messages);
        self::assertStringContainsString('green', $result->messages[0]);
        self::assertStringContainsString('main', $result->messages[0]);
    }

    public function testCheckOnException(): void
    {
        $client = self::createStub(Client::class);
        $client->method('getConfig')->willThrowException(new RuntimeException('boom'));

        $result = new ElasticaConnectionChecker($client, 'main')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertCount(1, $result->errors);
        self::assertStringContainsString('main', $result->errors[0]);
        self::assertStringContainsString('boom', $result->errors[0]);
    }

    public function testCheckRedactsCredentialsInFailureReason(): void
    {
        $client = self::createStub(Client::class);
        $client->method('getConfig')->willThrowException(new RuntimeException('Unreachable "https://elastic:s3cret@es:9200"'));

        $result = new ElasticaConnectionChecker($client, 'main')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Elastica connection (main) failed. Reason: Unreachable "https://***@es:9200"'], $result->errors);
    }
}
