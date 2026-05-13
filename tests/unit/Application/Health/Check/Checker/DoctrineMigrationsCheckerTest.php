<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\AvailableMigration;
use Doctrine\Migrations\Metadata\AvailableMigrationsList;
use Doctrine\Migrations\Version\MigrationStatusCalculator;
use Doctrine\Migrations\Version\Version;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\DoctrineMigrationsChecker;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

final class DoctrineMigrationsCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(DependencyFactory::class)) {
            self::markTestSkipped('doctrine/migrations is not installed');
        }
    }

    public function testIsSupportReadinessOnly(): void
    {
        $checker = new DoctrineMigrationsChecker($this->buildFactoryStub(newCount: 0));

        self::assertTrue($checker->isSupport(new Context(CheckTypeEnum::READINESS)));
        self::assertFalse($checker->isSupport(new Context(CheckTypeEnum::LIVELINESS)));
    }

    public function testCheckOnAllMigrationsApplied(): void
    {
        $checker = new DoctrineMigrationsChecker($this->buildFactoryStub(newCount: 0));

        $result = $checker->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Doctrine migrations passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCheckOnUnappliedMigrations(): void
    {
        $checker = new DoctrineMigrationsChecker($this->buildFactoryStub(newCount: 3));

        $result = $checker->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame([], $result->messages);
        self::assertCount(1, $result->errors);
        self::assertStringContainsString('3 unapplied', $result->errors[0]);
    }

    public function testCheckOnException(): void
    {
        $calculator = self::createStub(MigrationStatusCalculator::class);
        $calculator->method('getNewMigrations')->willThrowException(new RuntimeException('metadata table missing'));

        $factory = self::createStub(DependencyFactory::class);
        $factory->method('getMigrationStatusCalculator')->willReturn($calculator);

        $result = new DoctrineMigrationsChecker($factory)->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertCount(1, $result->errors);
        self::assertStringContainsString('metadata table missing', $result->errors[0]);
    }

    private function buildFactoryStub(int $newCount): DependencyFactory
    {
        $migrations = [];
        for ($i = 0; $i < $newCount; $i++) {
            $migrations[] = $this->buildAvailableMigration((string) $i);
        }
        $list = new AvailableMigrationsList($migrations);

        $calculator = self::createStub(MigrationStatusCalculator::class);
        $calculator->method('getNewMigrations')->willReturn($list);

        $factory = self::createStub(DependencyFactory::class);
        $factory->method('getMigrationStatusCalculator')->willReturn($calculator);

        return $factory;
    }

    private function buildAvailableMigration(string $id): AvailableMigration
    {
        $migration = new class(self::createStub(Connection::class), new NullLogger()) extends AbstractMigration {
            public function up(Schema $schema): void
            {
            }
        };

        return new AvailableMigration(new Version($id), $migration);
    }
}
