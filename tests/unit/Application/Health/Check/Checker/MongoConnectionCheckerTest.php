<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use ArrayIterator;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\MongoConnectionChecker;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use MongoDB\Client;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MongoConnectionCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Client::class)) {
            self::markTestSkipped('mongodb/mongodb is not installed');
        }
    }

    public function testIsSupportReadinessOnly(): void
    {
        $checker = new MongoConnectionChecker(self::createStub(Client::class), 'default');

        self::assertTrue($checker->isSupport(new Context(CheckTypeEnum::READINESS)));
        self::assertFalse($checker->isSupport(new Context(CheckTypeEnum::LIVELINESS)));
    }

    public function testCheckOnSuccess(): void
    {
        $client = self::createStub(Client::class);
        $client->method('listDatabaseNames')->willReturn(new ArrayIterator(['admin', 'app']));

        $result = new MongoConnectionChecker($client, 'default')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Mongo connection (default) passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCheckOnException(): void
    {
        $client = self::createStub(Client::class);
        $client->method('listDatabaseNames')->willThrowException(new RuntimeException('boom'));

        $result = new MongoConnectionChecker($client, 'default')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertCount(1, $result->errors);
        self::assertStringContainsString('default', $result->errors[0]);
        self::assertStringContainsString('boom', $result->errors[0]);
    }
}
