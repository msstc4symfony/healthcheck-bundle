<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Test\Unit\Application\Health\Check\Event;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\Event\SafeEventDispatcher;
use MaxShamaev\HealthCheckBundle\Test\Mock\Application\Health\Check\Event\RecordingDispatcherFixture;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use stdClass;

final class SafeEventDispatcherTest extends TestCase
{
    public function testDispatchesToInnerWhenPresent(): void
    {
        $inner = new RecordingDispatcherFixture();
        $event = new stdClass();

        new SafeEventDispatcher($inner)->dispatch($event);

        self::assertSame([$event], $inner->events);
    }

    public function testIsNoOpWithoutInner(): void
    {
        $this->expectNotToPerformAssertions();

        new SafeEventDispatcher()->dispatch(new stdClass());
    }

    public function testSwallowsListenerExceptions(): void
    {
        $this->expectNotToPerformAssertions();

        $exploding = new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                throw new RuntimeException('listener exploded');
            }
        };

        new SafeEventDispatcher($exploding)->dispatch(new stdClass());
    }
}
