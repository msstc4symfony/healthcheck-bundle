<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Application\Health\Check\DTO;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Response::class)]
final class ResponseTest extends TestCase
{
    public function testSucceedsWithoutErrors(): void
    {
        self::assertTrue((new Response([], ['ok'], ['slow']))->success);
        self::assertFalse((new Response(['down'], [], []))->success);
    }

    public function testSurvivesACacheRoundTrip(): void
    {
        $restored = unserialize(serialize(new Response(['down'], ['m'], ['w'])));

        self::assertInstanceOf(Response::class, $restored);
        self::assertFalse($restored->success);
        self::assertSame(['down'], $restored->errors);
    }
}
