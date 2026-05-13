<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToCheckExistence;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\FlysystemChecker;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FlysystemCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(FilesystemOperator::class)) {
            self::markTestSkipped('league/flysystem is not installed');
        }
    }

    public function testIsSupportReadinessOnly(): void
    {
        $checker = new FlysystemChecker(self::createStub(FilesystemOperator::class), 'uploads');

        self::assertTrue($checker->isSupport(new Context(CheckTypeEnum::READINESS)));
        self::assertFalse($checker->isSupport(new Context(CheckTypeEnum::LIVELINESS)));
    }

    public function testCheckOnSuccess(): void
    {
        $fs = self::createStub(FilesystemOperator::class);
        $fs->method('fileExists')->willReturn(false);

        $result = new FlysystemChecker($fs, 'uploads')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Flysystem (uploads) passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCheckOnException(): void
    {
        $fs = self::createStub(FilesystemOperator::class);
        $fs->method('fileExists')->willThrowException(UnableToCheckExistence::forLocation('.healthcheck-probe', new RuntimeException('S3 unreachable')));

        $result = new FlysystemChecker($fs, 'uploads')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertCount(1, $result->errors);
        self::assertStringContainsString('uploads', $result->errors[0]);
        self::assertStringContainsString('Unable to check existence', $result->errors[0]);
    }
}
