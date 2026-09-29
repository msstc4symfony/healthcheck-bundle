<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

#[AutoconfigureTag(CheckerDetectorInterface::TAG)]
interface CheckerDetectorInterface
{
    public const string TAG = 'healthcheck.detector';

    /**
     * Scan the container and yield checker service definitions keyed by service id.
     *
     * @return iterable<string, Definition>
     */
    public function detect(ContainerBuilder $container): iterable;
}
