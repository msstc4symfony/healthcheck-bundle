<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle;

use MaxShamaev\HealthCheckBundle\DependencyInjection\HealthCheckerAutoDetectionPass;
use MaxShamaev\HealthCheckBundle\DependencyInjection\HealthCheckerCriticalityDecorationPass;
use MaxShamaev\HealthCheckBundle\DependencyInjection\HealthCheckerTimeoutDecorationPass;
use MaxShamaev\HealthCheckBundle\DependencyInjection\HealthCheckExtension;
use Override;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class HealthCheckBundle extends Bundle
{
    #[Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Detectors discover themselves via the `healthcheck.detector` tag (autoconfigured by
        // CheckerDetectorInterface). Third-party bundles can contribute detectors by registering
        // services that implement the interface — no need to subclass HealthCheckBundle.
        $container->addCompilerPass(new HealthCheckerAutoDetectionPass());

        // Order matters: Timeout MUST wrap first so the outermost decorator is Criticality.
        // Final composition = NonCritical( Timeout( inner ) ). A non-critical checker that
        // exceeds its budget then produces a warning, not an error.
        $container->addCompilerPass(new HealthCheckerTimeoutDecorationPass());
        $container->addCompilerPass(new HealthCheckerCriticalityDecorationPass());
    }

    #[Override]
    public function getContainerExtension(): ExtensionInterface
    {
        return new HealthCheckExtension();
    }
}
