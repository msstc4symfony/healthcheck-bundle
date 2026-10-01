<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker;

use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

interface CountableMessengerTransportFixture extends TransportInterface, MessageCountAwareInterface
{
}
