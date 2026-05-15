<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Test\Unit;

use MSSTC4PHP\HealthCheckBundle\DependencyInjection\HealthCheckExtension;
use MSSTC4PHP\HealthCheckBundle\HealthCheckBundle;
use PHPUnit\Framework\TestCase;

final class HealthCheckBundleTest extends TestCase
{
    public function testGetContainerExtensionReturnsHealthCheckExtension(): void
    {
        $bundle = new HealthCheckBundle();

        self::assertInstanceOf(HealthCheckExtension::class, $bundle->getContainerExtension());
    }
}
