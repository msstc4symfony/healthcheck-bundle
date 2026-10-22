<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckerId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CheckerIdTest extends TestCase
{
    public function testAStringKeyIsTheServiceId(): void
    {
        self::assertSame('app.checker.redis', CheckerId::of('app.checker.redis'));
    }

    #[DataProvider('keysNamingNoService')]
    public function testAListOrEmptyKeyNamesNoService(int|string $key): void
    {
        self::assertNull(CheckerId::of($key));
    }

    /**
     * @return iterable<string, array{int|string}>
     */
    public static function keysNamingNoService(): iterable
    {
        yield 'list index' => [0];
        yield 'empty string' => [''];
    }
}
