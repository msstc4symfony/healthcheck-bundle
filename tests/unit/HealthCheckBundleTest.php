<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit;

use Msstc4Symfony\HealthCheckBundle\DependencyInjection\HealthCheckExtension;
use Msstc4Symfony\HealthCheckBundle\HealthCheckBundle;
use PHPUnit\Framework\TestCase;

final class HealthCheckBundleTest extends TestCase
{
    public function testGetContainerExtensionReturnsHealthCheckExtension(): void
    {
        $bundle = new HealthCheckBundle();

        self::assertInstanceOf(HealthCheckExtension::class, $bundle->getContainerExtension());
    }
}
