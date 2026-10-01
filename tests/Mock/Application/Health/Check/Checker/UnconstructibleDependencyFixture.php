<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker;

use RuntimeException;

/**
 * Stands in for a client whose constructor fails at runtime, e.g. SemaphoreStore without ext-sysvsem.
 */
final class UnconstructibleDependencyFixture
{
    public const string FAILURE = 'dependency cannot be constructed';

    public function __construct()
    {
        throw new RuntimeException(self::FAILURE);
    }

    public function ping(): string
    {
        return 'pong';
    }
}
