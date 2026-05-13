<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Test\Unit\Application\Health\Check;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\ActionInterface;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\CachedActionDecorator;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Request;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Response;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CachedActionDecoratorTest extends TestCase
{
    public function testFirstCallDelegatesToInner(): void
    {
        $expected = new Response([], ['inner ran']);
        $inner = new RecordingActionFixture($expected);

        $decorator = new CachedActionDecorator($inner, new ArrayAdapter(), 5);

        $actual = $decorator->run(new Request(CheckTypeEnum::READINESS));

        self::assertSame($expected, $actual);
        self::assertSame(1, $inner->callCount);
    }

    public function testSubsequentCallsUseCache(): void
    {
        $inner = new RecordingActionFixture(new Response([], ['inner ran']));
        $decorator = new CachedActionDecorator($inner, new ArrayAdapter(), 5);

        $decorator->run(new Request(CheckTypeEnum::READINESS));
        $decorator->run(new Request(CheckTypeEnum::READINESS));
        $decorator->run(new Request(CheckTypeEnum::READINESS));

        self::assertSame(1, $inner->callCount, 'second and third calls must be served from cache');
    }

    public function testCachesPerRequestType(): void
    {
        $inner = new RecordingActionFixture(new Response([], ['inner ran']));
        $decorator = new CachedActionDecorator($inner, new ArrayAdapter(), 5);

        $decorator->run(new Request(CheckTypeEnum::READINESS));
        $decorator->run(new Request(CheckTypeEnum::LIVELINESS));

        self::assertSame(2, $inner->callCount, 'readiness and liveliness must not share cache entries');
    }

    public function testCachesPerOptions(): void
    {
        $inner = new RecordingActionFixture(new Response([], ['inner ran']));
        $decorator = new CachedActionDecorator($inner, new ArrayAdapter(), 5);

        $decorator->run(new Request(CheckTypeEnum::READINESS, ['flavor' => 'fast']));
        $decorator->run(new Request(CheckTypeEnum::READINESS, ['flavor' => 'deep']));

        self::assertSame(2, $inner->callCount);
    }
}

final class RecordingActionFixture implements ActionInterface
{
    public int $callCount = 0;

    public function __construct(private readonly Response $response)
    {
    }

    public function run(Request $request): Response
    {
        $this->callCount++;

        return $this->response;
    }
}
