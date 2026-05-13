<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\EntityManagerChecker;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class EntityManagerCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(EntityManagerInterface::class)) {
            self::markTestSkipped('doctrine/orm is not installed');
        }
    }

    public function testIsSupportReadinessOnly(): void
    {
        $checker = new EntityManagerChecker($this->buildEntityManagerStub(connected: true), 'default');

        self::assertTrue($checker->isSupport(new Context(CheckTypeEnum::READINESS)));
        self::assertFalse($checker->isSupport(new Context(CheckTypeEnum::LIVELINESS)));
    }

    public function testCheckOnAlreadyConnected(): void
    {
        $em = $this->buildEntityManagerStub(connected: true);

        $result = new EntityManagerChecker($em, 'default')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['EntityManager (default) passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCheckForcesConnectionWhenDisconnected(): void
    {
        $em = $this->buildEntityManagerStub(connected: false);

        $result = new EntityManagerChecker($em, 'default')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['EntityManager (default) passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCheckOnBrokenMapping(): void
    {
        $em = $this->buildEntityManagerStub(connected: true, metadataException: new RuntimeException('mapping broken'));

        $result = new EntityManagerChecker($em, 'default')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertCount(1, $result->errors);
        self::assertStringContainsString('default', $result->errors[0]);
        self::assertStringContainsString('mapping broken', $result->errors[0]);
    }

    private function buildEntityManagerStub(bool $connected, ?Throwable $metadataException = null): EntityManagerInterface
    {
        $connection = self::createStub(Connection::class);
        $connection->method('isConnected')->willReturn($connected);
        $connection->method('getServerVersion')->willReturn('15.4');

        $metadataFactory = self::createStub(ClassMetadataFactory::class);
        if ($metadataException instanceof Throwable) {
            $metadataFactory->method('getAllMetadata')->willThrowException($metadataException);
        } else {
            $metadataFactory->method('getAllMetadata')->willReturn([]);
        }

        $em = self::createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        return $em;
    }
}
