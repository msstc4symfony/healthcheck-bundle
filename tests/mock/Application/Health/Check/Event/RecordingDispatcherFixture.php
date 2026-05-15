<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Test\Mock\Application\Health\Check\Event;

use Psr\EventDispatcher\EventDispatcherInterface;

final class RecordingDispatcherFixture implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        return $event;
    }
}
