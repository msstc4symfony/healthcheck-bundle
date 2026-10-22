<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Integration\Functional;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\ActionInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Request as CheckRequest;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Event\HealthCheckerCompletedEvent;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\ContainerIds;
use Msstc4Symfony\HealthCheckBundle\Test\Integration\Kernel\TestKernel;
use Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\SuccessChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;

/**
 * The checker id of an event is the service id behind the bundle's decorators, for a checker the application
 * registers and for one a detector synthesises, in both execution modes.
 */
final class CheckerEventIdTest extends TestCase
{
    #[DataProvider('executions')]
    public function testCheckerEventsCarryTheServiceIds(string $execution): void
    {
        if (!class_exists(LockFactory::class)) {
            self::markTestSkipped('symfony/lock is not installed');
        }

        (new Filesystem())->remove(sys_get_temp_dir() . '/msstc4symfony-healthcheck-bundle-test');
        $kernel = new TestKernel('checker_event_id_' . $execution, false, static function (ContainerConfigurator $container) use ($execution): void {
            $container->extension('framework', ['lock' => 'in-memory']);
            $container->extension(ContainerIds::ALIAS, ['execution' => $execution, 'non_critical' => ['app.checker.ok']]);
            $container->services()->set('app.checker.ok', SuccessChecker::class)->tag(CheckInterface::class);
        });
        $kernel->boot();

        try {
            $eventDispatcher = $kernel->getContainer()->get('event_dispatcher');
            self::assertInstanceOf(EventDispatcherInterface::class, $eventDispatcher);
            $ids = [];
            $eventDispatcher->addListener(HealthCheckerCompletedEvent::class, static function (HealthCheckerCompletedEvent $event) use (&$ids): void {
                $ids[] = [$event->checkerId, $event->type];
            });

            $testContainer = $kernel->getContainer()->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $testContainer);
            $action = $testContainer->get(ActionInterface::class);
            self::assertInstanceOf(ActionInterface::class, $action);
            $action->run(new CheckRequest(CheckTypeEnum::READINESS));

            self::assertContains(['app.checker.ok', CheckTypeEnum::READINESS], $ids);
            self::assertContains(['healthcheck.checker.lock.default', CheckTypeEnum::READINESS], $ids);
        } finally {
            $kernel->shutdown();
        }
    }

    /**
     * @return iterable<string, array{'sequential'|'parallel'}>
     */
    public static function executions(): iterable
    {
        yield 'sequential' => ['sequential'];
        yield 'parallel' => ['parallel'];
    }
}
