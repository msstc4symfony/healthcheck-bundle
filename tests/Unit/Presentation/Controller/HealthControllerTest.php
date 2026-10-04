<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Presentation\Controller;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Action;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\NonCriticalCheckerDecorator;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Event\SafeEventDispatcher;
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
        $controller = new HealthController(new Action([], new SafeEventDispatcher()), new NullLogger());

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
        $controller = new HealthController(new Action($checkers, new SafeEventDispatcher()), new NullLogger());

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
        $controller = new HealthController(new Action($checkers, new SafeEventDispatcher()), new NullLogger());

        $request = Request::create('http://localhost');
        $response = $controller->liveliness($request);

        self::assertSame($code, $response->getStatusCode());
    }

    public function testReadinessJson(): void
    {
        $controller = new HealthController(new Action([new SuccessChecker()], new SafeEventDispatcher()), new NullLogger());

        $request = Request::create('http://localhost');
        $request->setRequestFormat('json');

        $response = $controller->readiness($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('{"success":true,"errors":[],"messages":["success dump check"],"warnings":[]}', $response->getContent());
    }

    public function testTextOutputListsWarnings(): void
    {
        $controller = new HealthController(
            new Action([new SuccessChecker(), new NonCriticalCheckerDecorator(new FailChecker())], new SafeEventDispatcher()),
            new NullLogger(),
        );

        $response = $controller->readiness(Request::create('http://localhost'));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(
            "Result: up\nErrors: none\nMessages:\n\tsuccess dump check\nWarnings:\n\tfail dump check\n",
            $response->getContent(),
        );
    }

    public function testTextOutputTabIndentsEveryLineOfASection(): void
    {
        $controller = new HealthController(new Action([new SuccessChecker(), new FailChecker(), new FailChecker()], new SafeEventDispatcher()), new NullLogger());

        $response = $controller->readiness(Request::create('http://localhost'));

        self::assertSame(Response::HTTP_NOT_ACCEPTABLE, $response->getStatusCode());
        self::assertSame(
            "Result: down\nErrors:\n\tfail dump check\n\tfail dump check\nMessages:\n\tsuccess dump check\nWarnings: none\n",
            $response->getContent(),
        );
    }

    public function testTextOutputReportsNoWarnings(): void
    {
        $controller = new HealthController(new Action([new SuccessChecker()], new SafeEventDispatcher()), new NullLogger());

        self::assertStringEndsWith("Warnings: none\n", (string) $controller->readiness(Request::create('http://localhost'))->getContent());
    }

    public function testRouteFormatWinsOverQueryAndAcceptHeader(): void
    {
        $controller = new HealthController(new Action([new SuccessChecker()], new SafeEventDispatcher()), new NullLogger());

        $request = Request::create('http://localhost/?_format=json', server: ['HTTP_ACCEPT' => 'application/json']);
        $request->attributes->set('_format', 'txt');

        self::assertStringStartsWith('Result: up', (string) $controller->readiness($request)->getContent());
    }

    public function testNonStringFormatQueryParameterFallsBackToAcceptHeader(): void
    {
        $controller = new HealthController(new Action([new SuccessChecker()], new SafeEventDispatcher()), new NullLogger());

        $request = Request::create('http://localhost/?_format[]=json', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertSame('application/json', $controller->readiness($request)->headers->get('Content-Type'));
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
