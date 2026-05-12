<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\DependencyInjection\Detector;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

interface CheckerDetectorInterface
{
    /**
     * Scan the container and yield checker service definitions keyed by service id.
     *
     * @return iterable<string, Definition>
     */
    public function detect(ContainerBuilder $container): iterable;
}
