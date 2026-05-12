<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\DependencyInjection;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use MaxShamaev\HealthCheckBundle\DependencyInjection\Detector\CheckerDetectorInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final readonly class HealthCheckerAutoDetectionPass implements CompilerPassInterface
{
    /** @var list<CheckerDetectorInterface> */
    private array $detectors;

    /**
     * @param iterable<CheckerDetectorInterface> $detectors
     */
    public function __construct(iterable $detectors)
    {
        $this->detectors = is_array($detectors)
            ? array_values($detectors)
            : iterator_to_array($detectors, false);
    }

    public function process(ContainerBuilder $container): void
    {
        foreach ($this->detectors as $detector) {
            foreach ($detector->detect($container) as $id => $definition) {
                $this->register($container, $id, $definition);
            }
        }
    }

    private function register(ContainerBuilder $container, string $id, Definition $definition): void
    {
        $definition->setAutowired(true);
        $definition->addTag(CheckInterface::class);

        $container->setDefinition($id, $definition);
    }
}
