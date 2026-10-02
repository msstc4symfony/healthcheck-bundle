<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * A detector whose checker target may only wrap a service another checker already probes.
 *
 * @internal
 */
interface WrappedTargetDetectorInterface
{
    /**
     * The id of the service wrapped by the target of a checker this detector yielded, or null.
     */
    public function wrappedTarget(ContainerBuilder $container, Definition $checker): ?string;
}
