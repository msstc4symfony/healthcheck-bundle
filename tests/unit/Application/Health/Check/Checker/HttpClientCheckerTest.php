<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Test\Unit\Application\Health\Check\Checker;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\HttpClientChecker;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\HttpProbeTarget;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class HttpClientCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(HttpClientInterface::class)) {
            self::markTestSkipped('symfony/http-client is not installed');
        }
    }

    public function testIsSupportReadinessOnly(): void
    {
        $checker = $this->buildChecker(self::createStub(HttpClientInterface::class));

        self::assertTrue($checker->isSupport(new Context(CheckTypeEnum::READINESS)));
        self::assertFalse($checker->isSupport(new Context(CheckTypeEnum::LIVELINESS)));
    }

    public function testCheckOnSuccess(): void
    {
        $response = self::createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $client = self::createStub(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $result = $this->buildChecker($client)->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertSame(['HTTP probe (upstream) passed'], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testCheckOnUnexpectedStatusCode(): void
    {
        $response = self::createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(503);

        $client = self::createStub(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $result = $this->buildChecker($client)->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertCount(1, $result->errors);
        self::assertStringContainsString('unexpected status 503', $result->errors[0]);
    }

    public function testCheckOnTransportExceptionEmitsSanitizedMessage(): void
    {
        $client = self::createStub(HttpClientInterface::class);
        // Symfony HTTP exceptions often include the full URL (incl. ?token=...). The checker
        // must NOT leak this to the response body.
        $client->method('request')->willThrowException(new RuntimeException('Could not resolve host: api.example.com?token=secret-token'));

        $result = $this->buildChecker($client)->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertCount(1, $result->errors);
        self::assertStringContainsString('HTTP probe transport error', $result->errors[0]);
        self::assertStringNotContainsString('secret-token', $result->errors[0]);
        self::assertStringNotContainsString('?token=', $result->errors[0]);
    }

    private function buildChecker(HttpClientInterface $client): HttpClientChecker
    {
        return new HttpClientChecker(
            $client,
            'upstream',
            new HttpProbeTarget('https://api.example.com/health', 'GET', [200, 204], 3),
        );
    }
}
