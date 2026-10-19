<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services->defaults()->autowire()->autoconfigure();

    // Concrete checkers come from detectors or the application. The interfaces stay in the scan:
    // their #[AutoconfigureTag] is registered only for interfaces the loader has seen.
    $services->load('Msstc4Symfony\\HealthCheckBundle\\', '../../')
        ->exclude([
            '../../DependencyInjection/*.php',
            '../../DependencyInjection/CompilerPass/',
            '../../Application/Health/Check/Checker/{*Checker,*Decorator,CheckerClass}.php',
            '../../Infrastructure/Checker/',
            '../../Resources/',
            '../../HealthCheckBundle.php',
        ])
    ;
};
