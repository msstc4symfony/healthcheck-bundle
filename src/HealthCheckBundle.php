<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle;

use MaxShamaev\HealthCheckBundle\DependencyInjection\HealthCheckExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class HealthCheckBundle extends Bundle
{
    public function getContainerExtension(): ExtensionInterface
    {
        return new HealthCheckExtension();
    }
}
