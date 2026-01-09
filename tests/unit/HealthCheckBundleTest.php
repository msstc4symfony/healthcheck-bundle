<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Test\Unit;

use MaxShamaev\HealthCheckBundle\DependencyInjection\HealthCheckExtension;
use MaxShamaev\HealthCheckBundle\HealthCheckBundle;
use PHPUnit\Framework\TestCase;

final class HealthCheckBundleTest extends TestCase
{
    public function testGetContainerExtensionReturnsHealthCheckExtension(): void
    {
        $bundle = new HealthCheckBundle();

        self::assertInstanceOf(HealthCheckExtension::class, $bundle->getContainerExtension());
    }
}
