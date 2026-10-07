<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\PredisChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use Predis\Response\Error;
use Predis\Response\ResponseInterface;
use Predis\Response\ServerException;
use Predis\Response\Status;

final class PredisCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Client::class)) {
            self::markTestSkipped('predis/predis is not installed');
        }
    }

    public function testIsSupportReadinessOnly(): void
    {
        $checker = new PredisChecker(self::createStub(Client::class));

        self::assertTrue($checker->isSupport(new Context(CheckTypeEnum::READINESS)));
        self::assertFalse($checker->isSupport(new Context(CheckTypeEnum::LIVELINESS)));
    }

    public function testCheckWritesTheProbeKeyWithOneSecondTtl(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::once())
            ->method('__call')
            ->with('set', self::callback(static fn (array $arguments): bool => $arguments[0] === CheckInterface::PROBE_KEY
                && is_string($arguments[1]) && ctype_digit($arguments[1])
                && array_slice($arguments, 2) === ['EX', 1]))
            ->willReturn(new Status('OK'))
        ;

        $result = new PredisChecker($client)->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Redis connection passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    /**
     * @return iterable<string, array{?ResponseInterface, non-empty-string}>
     */
    public static function nonOkReplies(): iterable
    {
        yield 'queued status' => [new Status('QUEUED'), 'Redis connection failed (SET command returned QUEUED)'];
        yield 'error reply without exceptions' => [new Error("READONLY You can't write against a read only replica."), "Redis connection failed (SET command returned READONLY You can't write against a read only replica.)"];
        yield 'no reply' => [null, 'Redis connection failed (SET command returned null)'];
    }

    #[DataProvider('nonOkReplies')]
    public function testCheckFailsOnNonOkReply(?ResponseInterface $reply, string $expectedError): void
    {
        $client = self::createStub(Client::class);
        $client->method('__call')->willReturn($reply);

        $result = new PredisChecker($client)->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame([$expectedError], $result->errors);
        self::assertSame([], $result->messages);
    }

    public function testCheckFailsOnServerException(): void
    {
        $client = self::createStub(Client::class);
        $client->method('__call')->willThrowException(new ServerException('NOAUTH Authentication required.'));

        $result = new PredisChecker($client)->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['Redis connection failed (NOAUTH Authentication required.)'], $result->errors);
        self::assertSame([], $result->messages);
    }

    public function testCheckFailsWhenNoServerListens(): void
    {
        $client = new Client('tcp://127.0.0.1:1', ['parameters' => ['timeout' => 1]]);

        $result = new PredisChecker($client)->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame([], $result->messages);
        self::assertCount(1, $result->errors);
        self::assertStringStartsWith('Redis connection failed (', $result->errors[0]);
    }
}
