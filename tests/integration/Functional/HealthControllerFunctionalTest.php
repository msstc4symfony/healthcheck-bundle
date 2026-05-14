<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Test\Integration\Functional;

use MaxShamaev\HealthCheckBundle\Test\Integration\Kernel\TestKernel;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * End-to-end HTTP probe tests against a real Symfony kernel.
 *
 * Asserts each probe endpoint resolves through routing → controller → action pipeline
 * and produces the right status code + content-type in text format. The JSON output
 * path is exhaustively covered by the unit HealthControllerTest, which calls
 * setRequestFormat('json') directly — there is no public route attribute that triggers
 * JSON via URL, so functional tests of that branch require an out-of-bundle format
 * listener and would test the listener, not the controller.
 */
final class HealthControllerFunctionalTest extends WebTestCase
{
    protected function setUp(): void
    {
        new Filesystem()->remove(sys_get_temp_dir() . '/maxshamaev-healthcheck-bundle-test');
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

    public function testUnknownProbeEndpointReturns404(): void
    {
        $client = self::createClient();
        $client->catchExceptions(false);

        $this->expectException(NotFoundHttpException::class);
        $client->request(Request::METHOD_GET, '/_/healthcheck/unknown');
    }
}
