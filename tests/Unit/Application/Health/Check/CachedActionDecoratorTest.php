<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Application\Health\Check;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\ActionInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\CachedActionDecorator;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Request;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Response;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CachedActionDecoratorTest extends TestCase
{
    public function testFirstCallDelegatesToInner(): void
    {
        $expected = new Response([], ['inner ran'], []);
        $inner = new RecordingActionFixture($expected);

        $decorator = new CachedActionDecorator($inner, new ArrayAdapter(), 5);

        $actual = $decorator->run(new Request(CheckTypeEnum::READINESS));

        self::assertSame($expected, $actual);
        self::assertSame(1, $inner->callCount);
    }

    public function testSubsequentCallsUseCache(): void
    {
        $inner = new RecordingActionFixture(new Response([], ['inner ran'], []));
        $decorator = new CachedActionDecorator($inner, new ArrayAdapter(), 5);

        $decorator->run(new Request(CheckTypeEnum::READINESS));
        $decorator->run(new Request(CheckTypeEnum::READINESS));
        $decorator->run(new Request(CheckTypeEnum::READINESS));

        self::assertSame(1, $inner->callCount, 'second and third calls must be served from cache');
    }

    public function testCachesPerRequestType(): void
    {
        $inner = new RecordingActionFixture(new Response([], ['inner ran'], []));
        $decorator = new CachedActionDecorator($inner, new ArrayAdapter(), 5);

        $decorator->run(new Request(CheckTypeEnum::READINESS));
        $decorator->run(new Request(CheckTypeEnum::LIVELINESS));

        self::assertSame(2, $inner->callCount, 'readiness and liveliness must not share cache entries');
    }

    public function testCachesPerOptions(): void
    {
        $inner = new RecordingActionFixture(new Response([], ['inner ran'], []));
        $decorator = new CachedActionDecorator($inner, new ArrayAdapter(), 5);

        $decorator->run(new Request(CheckTypeEnum::READINESS, ['flavor' => 'fast']));
        $decorator->run(new Request(CheckTypeEnum::READINESS, ['flavor' => 'deep']));

        self::assertSame(2, $inner->callCount);
    }

    public function testFailingResponseIsNotCached(): void
    {
        $inner = new RecordingActionFixture(new Response(['broke'], [], []));
        $cache = new ArrayAdapter();
        $decorator = new CachedActionDecorator($inner, $cache, 5);

        $decorator->run(new Request(CheckTypeEnum::READINESS));
        $decorator->run(new Request(CheckTypeEnum::READINESS));
        $decorator->run(new Request(CheckTypeEnum::READINESS));

        self::assertSame(3, $inner->callCount, 'failures must not be cached: every call re-runs the inner');
    }

    public function testOptionsKeyIsStableAcrossKeyOrder(): void
    {
        $inner = new RecordingActionFixture(new Response([], ['inner ran'], []));
        $decorator = new CachedActionDecorator($inner, new ArrayAdapter(), 5);

        $decorator->run(new Request(CheckTypeEnum::READINESS, ['a' => 1, 'b' => 2]));
        $decorator->run(new Request(CheckTypeEnum::READINESS, ['b' => 2, 'a' => 1]));

        self::assertSame(1, $inner->callCount, 'options arrays with the same keys but different order must hit the same cache entry');
    }

    public function testCachePollutionFallsThroughAndEvicts(): void
    {
        $inner = new RecordingActionFixture(new Response([], ['inner ran'], []));
        $cache = new ArrayAdapter();

        // Seed the same cache key with a foreign value (simulates another consumer of the pool).
        $polluted = $cache->getItem('msstc4symfony_healthcheck.readiness.' . hash('sha256', json_encode([], JSON_THROW_ON_ERROR)));
        $polluted->set(new stdClass());

        $cache->save($polluted);

        $decorator = new CachedActionDecorator($inner, $cache, 5);

        $response = $decorator->run(new Request(CheckTypeEnum::READINESS));

        self::assertSame(1, $inner->callCount, 'polluted entry must not be served — inner must run');
        self::assertTrue($response->success);

        // On the next call the legit value should now be cached.
        $decorator->run(new Request(CheckTypeEnum::READINESS));
        self::assertSame(1, $inner->callCount, 'after the success run, the cache must hold a legit Response');
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
