<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Application\Health\Check;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\CredentialRedactor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CredentialRedactorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function messages(): iterable
    {
        yield 'user and password' => ['Connection to "redis://admin:s3cret@redis:6379/0" failed', 'Connection to "redis://***@redis:6379/0" failed'];
        yield 'password only' => ['Store "redis://:s3cret@redis:6379" is down', 'Store "redis://***@redis:6379" is down'];
        yield 'several urls' => ['amqp://u:p1@a:5672 and mysql://root:p2@db/app', 'amqp://***@a:5672 and mysql://***@db/app'];
        yield 'secret query parameters' => ['https://es:9200/?api_key=k3y&timeout=1&password=p4ss', 'https://es:9200/?api_key=***&timeout=1&password=***'];
        yield 'no credentials' => ['Semaphore extension (sysvsem) is required.', 'Semaphore extension (sysvsem) is required.'];
        yield 'url without user info' => ['redis://redis:6379/0 refused', 'redis://redis:6379/0 refused'];
        yield 'e-mail address outside url' => ['contact ops@example.com', 'contact ops@example.com'];
    }

    #[DataProvider('messages')]
    public function testRedact(string $message, string $expected): void
    {
        self::assertSame($expected, CredentialRedactor::redact($message));
    }
}
