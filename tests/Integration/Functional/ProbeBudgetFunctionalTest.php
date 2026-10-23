<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Integration\Functional;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\ActionInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Request as CheckRequest;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Response as CheckResponse;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Msstc4Symfony\HealthCheckBundle\Test\Integration\Kernel\TestKernel;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\SlowChecker;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;

/**
 * timeouts.probe_ms reaches the runner through the container: the first slow checker spends the
 * budget, the second is skipped, whichever the execution mode.
 */
final class ProbeBudgetFunctionalTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir() . '/msstc4symfony-healthcheck-bundle-test');
    }

    /**
     * @return iterable<string, array{'sequential'|'parallel'}>
     */
    public static function executions(): iterable
    {
        yield 'sequential' => ['sequential'];
        yield 'parallel' => ['parallel'];
    }

    /**
     * @param 'sequential'|'parallel' $execution
     */
    #[DataProvider('executions')]
    public function testSkipsTheCheckerStartedAfterTheBudgetIsSpent(string $execution): void
    {
        $response = $this->runReadiness('probe_budget_' . $execution, ['execution' => $execution, 'timeouts' => ['probe_ms' => 50]]);

        self::assertSame(['Slow (first) passed'], $this->slowLines($response->messages));
        $errors = $this->slowLines($response->errors);
        self::assertCount(1, $errors);
        self::assertMatchesRegularExpression('/^Slow \(second\) skipped: probe budget exhausted \(\d+ ms > 50 ms\)$/', $errors[0]);
    }

    public function testRunsEveryCheckerWithoutABudget(): void
    {
        $response = $this->runReadiness('probe_budget_unlimited', []);

        self::assertSame(['Slow (first) passed', 'Slow (second) passed'], $this->slowLines($response->messages));
        self::assertSame([], $this->slowLines($response->errors));
    }

    /**
     * Components installed as runtime dependencies (as in CI) add checkers of their own, e.g. the lock store.
     *
     * @param array<array-key, string> $lines
     *
     * @return list<string>
     */
    private function slowLines(array $lines): array
    {
        return array_values(array_filter($lines, static fn (string $line): bool => str_starts_with($line, 'Slow (')));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function runReadiness(string $environment, array $config): CheckResponse
    {
        $kernel = new TestKernel($environment, false, static function (ContainerConfigurator $container) use ($config): void {
            $container->extension('msstc4symfony_healthcheck', $config);
            $container->services()
                ->set('app.slow_first', SlowChecker::class)->args([200, 'first'])->tag(CheckInterface::class)
                ->set('app.slow_second', SlowChecker::class)->args([0, 'second'])->tag(CheckInterface::class)
            ;
        });
        $kernel->boot();

        try {
            $testContainer = $kernel->getContainer()->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $testContainer);
            $action = $testContainer->get(ActionInterface::class);
            self::assertInstanceOf(ActionInterface::class, $action);

            return $action->run(new CheckRequest(CheckTypeEnum::READINESS));
        } finally {
            $kernel->shutdown();
        }
    }
}
