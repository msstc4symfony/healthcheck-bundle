<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Presentation\Controller;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Action;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Presentation\Controller\HealthController;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\FailChecker;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\SuccessChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class HealthControllerTest extends TestCase
{
    public function testPing(): void
    {
        $controller = new HealthController(new Action([]), new NullLogger());

        $response = $controller->ping();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('pong', $response->getContent());
    }

    /**
     * @param CheckInterface[] $checkers
     */
    #[DataProvider('getDataForTest')]
    public function testReadiness(array $checkers, int $code): void
    {
        $controller = new HealthController(new Action($checkers), new NullLogger());

        $request = Request::create('http://localhost');
        $response = $controller->readiness($request);

        self::assertSame($code, $response->getStatusCode());
    }

    /**
     * @param CheckInterface[] $checkers
     */
    #[DataProvider('getDataForTest')]
    public function testLiveliness(array $checkers, int $code): void
    {
        $controller = new HealthController(new Action($checkers), new NullLogger());

        $request = Request::create('http://localhost');
        $response = $controller->liveliness($request);

        self::assertSame($code, $response->getStatusCode());
    }

    public function testReadinessJson(): void
    {
        $controller = new HealthController(new Action([new SuccessChecker()]), new NullLogger());

        $request = Request::create('http://localhost');
        $request->setRequestFormat('json');

        $response = $controller->readiness($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('{"success":true,"errors":[],"messages":["success dump check"],"warnings":[]}', $response->getContent());
    }

    /**
     * @return array<string, array{checkers: CheckInterface[], code: int}>
     */
    public static function getDataForTest(): array
    {
        return [
            'success empty' => [
                'checkers' => [],
                'code' => Response::HTTP_OK,
            ],
            'success' => [
                'checkers' => [
                    new SuccessChecker(),
                ],
                'code' => Response::HTTP_OK,
            ],
            'failed' => [
                'checkers' => [
                    new FailChecker(),
                ],
                'code' => Response::HTTP_NOT_ACCEPTABLE,
            ],
        ];
    }
}
