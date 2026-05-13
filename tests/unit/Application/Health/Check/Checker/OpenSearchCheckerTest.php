<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\OpenSearchChecker;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use OpenSearch\Client;
use OpenSearch\Namespaces\ClusterNamespace;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OpenSearchCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Client::class)) {
            self::markTestSkipped('opensearch-project/opensearch-php is not installed');
        }
    }

    public function testIsSupportReadinessOnly(): void
    {
        $checker = new OpenSearchChecker(self::createStub(Client::class), 'main');

        self::assertTrue($checker->isSupport(new Context(CheckTypeEnum::READINESS)));
        self::assertFalse($checker->isSupport(new Context(CheckTypeEnum::LIVELINESS)));
    }

    public function testCheckOnSuccess(): void
    {
        $cluster = self::createStub(ClusterNamespace::class);
        $cluster->method('health')->willReturn(['status' => 'green']);

        $client = self::createStub(Client::class);
        $client->method('cluster')->willReturn($cluster);

        $result = new OpenSearchChecker($client, 'main')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['OpenSearch (main) passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCheckOnException(): void
    {
        $cluster = self::createStub(ClusterNamespace::class);
        $cluster->method('health')->willThrowException(new RuntimeException('cluster unavailable'));

        $client = self::createStub(Client::class);
        $client->method('cluster')->willReturn($cluster);

        $result = new OpenSearchChecker($client, 'main')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertCount(1, $result->errors);
        self::assertStringContainsString('main', $result->errors[0]);
        self::assertStringContainsString('cluster unavailable', $result->errors[0]);
    }
}
