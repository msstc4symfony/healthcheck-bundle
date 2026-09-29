<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\EntityManagerChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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
        $checker = new EntityManagerChecker($this->buildEntityManagerStub(), 'default');

        self::assertTrue($checker->isSupport(new Context(CheckTypeEnum::READINESS)));
        self::assertFalse($checker->isSupport(new Context(CheckTypeEnum::LIVELINESS)));
    }

    public function testCheckOnSuccess(): void
    {
        $em = $this->buildEntityManagerStub();

        $result = new EntityManagerChecker($em, 'default')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['EntityManager (default) passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCheckOnException(): void
    {
        $platform = self::createStub(AbstractPlatform::class);
        $platform->method('getDummySelectSQL')->willReturn('SELECT 1');

        $connection = self::createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $connection->method('executeQuery')->willThrowException(new RuntimeException('connection refused'));

        $em = self::createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);

        $result = new EntityManagerChecker($em, 'default')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertCount(1, $result->errors);
        self::assertStringContainsString('default', $result->errors[0]);
        self::assertStringContainsString('connection refused', $result->errors[0]);
    }

    private function buildEntityManagerStub(): EntityManagerInterface
    {
        $platform = self::createStub(AbstractPlatform::class);
        $platform->method('getDummySelectSQL')->willReturn('SELECT 1');

        $connection = self::createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        $em = self::createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);

        return $em;
    }
}
