<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle;

use MaxShamaev\HealthCheckBundle\DependencyInjection\HealthCheckExtension;
use Override;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class HealthCheckBundle extends Bundle
{
    #[Override]
    public function getContainerExtension(): ExtensionInterface
    {
        return new HealthCheckExtension();
    }
}
