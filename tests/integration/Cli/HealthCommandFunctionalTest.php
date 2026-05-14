<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Test\Integration\Cli;

use MaxShamaev\HealthCheckBundle\Test\Integration\Kernel\TestKernel;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Asserts both CLI commands are discoverable through Symfony's Application and run
 * end-to-end against the real container. Complements the unit Command tests, which
 * skip Application registration.
 */
final class HealthCommandFunctionalTest extends KernelTestCase
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

    public function testReadinessCommandRunsToCompletion(): void
    {
        $tester = $this->runCommand('healthcheck:readiness');

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Result: success', $tester->getDisplay());
    }

    public function testLivelinessCommandRunsToCompletion(): void
    {
        $tester = $this->runCommand('healthcheck:liveliness');

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Result: success', $tester->getDisplay());
    }

    public function testBothCommandsAreRegisteredWithApplication(): void
    {
        $application = new Application(self::bootKernel());

        self::assertTrue($application->has('healthcheck:liveliness'));
        self::assertTrue($application->has('healthcheck:readiness'));
    }

    private function runCommand(string $name): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $tester = new CommandTester($application->find($name));
        $tester->execute([]);

        return $tester;
    }
}
