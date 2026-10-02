<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Application\Health\Check;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Action;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\NonCriticalCheckerDecorator;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Request;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\FailChecker;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\SuccessChecker;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\ThrowingChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ActionTest extends TestCase
{
    /**
     * @param CheckInterface[] $checkers
     * @param string[] $messages
     * @param string[] $errors
     */
    #[DataProvider('getDataForTestRun')]
    public function testRun(array $checkers, Request $request, bool $success, array $messages, array $errors): void
    {
        $action = new Action($checkers);
        $response = $action->run($request);

        self::assertSame($response->success, $success);
        self::assertSame($response->messages, $messages);
        self::assertSame($response->errors, $errors);
    }

    public function testNonCriticalCheckerExceptionBecomesAWarningAndTheRunContinues(): void
    {
        $action = new Action([
            new NonCriticalCheckerDecorator(new ThrowingChecker('boom')),
            new SuccessChecker(),
        ]);

        $response = $action->run(new Request(CheckTypeEnum::READINESS));

        self::assertTrue($response->success);
        self::assertSame([], $response->errors);
        self::assertSame(['success dump check'], $response->messages);
        self::assertSame([sprintf('%s failed (boom)', ThrowingChecker::class)], $response->warnings);
    }

    public function testCriticalCheckerExceptionStillEscapesTheSequentialRun(): void
    {
        $action = new Action([new ThrowingChecker('boom'), new SuccessChecker()]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $action->run(new Request(CheckTypeEnum::READINESS));
    }

    /**
     * @return array<string, array{checkers: CheckInterface[], request: Request, success: bool, messages: string[], errors?: string[]}>
     */
    public static function getDataForTestRun(): array
    {
        return [
            'success empty readiness' => [
                'checkers' => [],
                'request' => new Request(CheckTypeEnum::READINESS),
                'success' => true,
                'messages' => [],
                'errors' => [],
            ],
            'success readiness' => [
                'checkers' => [
                    new SuccessChecker(),
                ],
                'request' => new Request(CheckTypeEnum::READINESS),
                'success' => true,
                'messages' => ['success dump check'],
                'errors' => [],
            ],
            'failed readiness' => [
                'checkers' => [
                    new FailChecker(),
                ],
                'request' => new Request(CheckTypeEnum::READINESS),
                'success' => false,
                'messages' => [],
                'errors' => ['fail dump check'],
            ],
            'success empty liveliness' => [
                'checkers' => [],
                'request' => new Request(CheckTypeEnum::LIVELINESS),
                'success' => true,
                'messages' => [],
                'errors' => [],
            ],
            'success liveliness' => [
                'checkers' => [
                    new SuccessChecker(),
                ],
                'request' => new Request(CheckTypeEnum::LIVELINESS),
                'success' => true,
                'messages' => ['success dump check'],
                'errors' => [],
            ],
            'failed liveliness' => [
                'checkers' => [
                    new FailChecker(),
                ],
                'request' => new Request(CheckTypeEnum::LIVELINESS),
                'success' => false,
                'messages' => [],
                'errors' => ['fail dump check'],
            ],
        ];
    }
}
