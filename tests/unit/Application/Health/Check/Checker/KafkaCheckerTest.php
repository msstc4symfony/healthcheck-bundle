<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\KafkaChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use RdKafka\Metadata;
use RdKafka\Producer;
use RuntimeException;

#[RequiresPhpExtension('rdkafka')]
final class KafkaCheckerTest extends TestCase
{
    public function testIsSupportReadinessOnly(): void
    {
        $checker = new KafkaChecker(self::createStub(Producer::class), 'events');

        self::assertTrue($checker->isSupport(new Context(CheckTypeEnum::READINESS)));
        self::assertFalse($checker->isSupport(new Context(CheckTypeEnum::LIVELINESS)));
    }

    public function testCheckOnSuccess(): void
    {
        $producer = self::createStub(Producer::class);
        $producer->method('getMetadata')->willReturn(self::createStub(Metadata::class));

        $result = new KafkaChecker($producer, 'events')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Kafka (events) passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCheckOnException(): void
    {
        $producer = self::createStub(Producer::class);
        $producer->method('getMetadata')->willThrowException(new RuntimeException('all brokers down'));

        $result = new KafkaChecker($producer, 'events')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertCount(1, $result->errors);
        self::assertStringContainsString('events', $result->errors[0]);
        self::assertStringContainsString('all brokers down', $result->errors[0]);
    }
}
