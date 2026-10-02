<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Integration\Functional;

use Msstc4Symfony\HealthCheckBundle\Test\Integration\Kernel\TestKernel;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * End-to-end HTTP probe tests against a real Symfony kernel.
 *
 * Asserts each probe endpoint resolves through routing → controller → action pipeline
 * and produces the right status code + content-type in text and JSON formats.
 */
final class HealthControllerFunctionalTest extends WebTestCase
{
    protected function setUp(): void
    {
        if (!class_exists(AbstractBrowser::class)) {
            self::markTestSkipped('symfony/browser-kit is not installed');
        }

        new Filesystem()->remove(sys_get_temp_dir() . '/msstc4symfony-healthcheck-bundle-test');
    }

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): TestKernel
    {
        // Debug mode is intentionally OFF: it activates Symfony's debug logger which
        // prints to stdout and trips beStrictAboutOutputDuringTests. Routing/container
        // wiring is fully exercised without debug.
        $environment = \is_string($options['environment'] ?? null) ? $options['environment'] : 'test';
        $debug = \is_bool($options['debug'] ?? null) && $options['debug'];

        return new TestKernel($environment, $debug);
    }

    public function testPingReturnsPong(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/_/healthcheck/ping');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('pong', $response->getContent());
    }

    public function testReadinessReturnsOk(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/_/healthcheck/readiness');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('Result: up', (string) $response->getContent());
    }

    public function testLivelinessReturnsOk(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/_/healthcheck/liveliness');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('Result: up', (string) $response->getContent());
    }

    public function testReadinessDefaultsToPlainText(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/_/healthcheck/readiness', server: ['HTTP_ACCEPT' => '*/*']);

        $response = $client->getResponse();
        self::assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
        self::assertStringStartsWith('Result: up', (string) $response->getContent());
    }

    public function testReadinessReturnsJsonForFormatQueryParameter(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/_/healthcheck/readiness?_format=json');

        $this->assertJsonProbe($client->getResponse());
    }

    public function testLivelinessReturnsJsonForAcceptHeader(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/_/healthcheck/liveliness', server: ['HTTP_ACCEPT' => 'application/json']);

        $this->assertJsonProbe($client->getResponse());
    }

    public function testFormatQueryParameterWinsOverAcceptHeader(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/_/healthcheck/readiness?_format=txt', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertStringStartsWith('Result: up', (string) $client->getResponse()->getContent());
    }

    public function testUnknownProbeEndpointReturns404(): void
    {
        $client = self::createClient();
        $client->catchExceptions(false);

        $this->expectException(NotFoundHttpException::class);
        $client->request(Request::METHOD_GET, '/_/healthcheck/unknown');
    }

    private function assertJsonProbe(Response $response): void
    {
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        $payload = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertTrue($payload['success']);
        self::assertArrayHasKey('warnings', $payload);
    }
}
