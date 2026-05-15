<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use Doctrine\ODM\MongoDB\Configuration;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\Client;
use MongoDB\Database;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\ODMConnectionChecker;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ODMConnectionCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(DocumentManager::class)) {
            self::markTestSkipped('doctrine/mongodb-odm is not installed');
        }
    }

    public function testIsSupportReadinessOnly(): void
    {
        $checker = new ODMConnectionChecker(self::createStub(DocumentManager::class), 'default');

        self::assertTrue($checker->isSupport(new Context(CheckTypeEnum::READINESS)));
        self::assertFalse($checker->isSupport(new Context(CheckTypeEnum::LIVELINESS)));
    }

    public function testCheckOnSuccess(): void
    {
        $dm = $this->buildDocumentManagerStub('app_db', ok: true);

        $result = new ODMConnectionChecker($dm, 'default')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['ODM connection (default) passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCheckFallsBackToAdminWhenNoDefaultDb(): void
    {
        $database = self::createStub(Database::class);

        $client = $this->createMock(Client::class);
        $client->expects(self::once())
            ->method('selectDatabase')
            ->with('admin')
            ->willReturn($database)
        ;

        $configuration = self::createStub(Configuration::class);
        $configuration->method('getDefaultDB')->willReturn(null);

        $dm = self::createStub(DocumentManager::class);
        $dm->method('getConfiguration')->willReturn($configuration);
        $dm->method('getClient')->willReturn($client);

        $result = new ODMConnectionChecker($dm, 'default')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['ODM connection (default) passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCheckOnException(): void
    {
        $configuration = self::createStub(Configuration::class);
        $configuration->method('getDefaultDB')->willThrowException(new RuntimeException('boom'));

        $dm = self::createStub(DocumentManager::class);
        $dm->method('getConfiguration')->willReturn($configuration);

        $result = new ODMConnectionChecker($dm, 'default')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame([], $result->messages);
        self::assertCount(1, $result->errors);
        self::assertStringContainsString('default', $result->errors[0]);
        self::assertStringContainsString('boom', $result->errors[0]);
    }

    private function buildDocumentManagerStub(string $defaultDb, bool $ok): DocumentManager
    {
        $database = self::createStub(Database::class);
        if (!$ok) {
            $database->method('command')->willThrowException(new RuntimeException('boom'));
        }

        $client = self::createStub(Client::class);
        $client->method('selectDatabase')->willReturn($database);

        $configuration = self::createStub(Configuration::class);
        $configuration->method('getDefaultDB')->willReturn($defaultDb);

        $dm = self::createStub(DocumentManager::class);
        $dm->method('getConfiguration')->willReturn($configuration);
        $dm->method('getClient')->willReturn($client);

        return $dm;
    }
}
