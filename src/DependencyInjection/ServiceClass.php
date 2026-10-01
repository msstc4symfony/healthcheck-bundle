<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\DependencyInjection;

use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Type checks against arbitrary service definitions of the application.
 *
 * is_subclass_of() autoloads the class, and a class whose parent comes from a package that is
 * not installed (e.g. security-core's UserPasswordValidator without symfony/validator) is a
 * fatal error. The container's reflection survives that and also resolves "%param%" classes.
 */
final class ServiceClass
{
    /**
     * @param string $type class or interface name; may name a type that is not installed
     */
    public static function is(ContainerBuilder $container, Definition $definition, string $type): bool
    {
        $class = $container->getParameterBag()->resolveValue($definition->getClass());
        if (!is_string($class)) {
            return false;
        }

        // Without the type (e.g. ext-rdkafka missing) only the exact class name can match.
        if (!class_exists($type) && !interface_exists($type)) {
            return ltrim($class, '\\') === $type;
        }

        $reflection = $container->getReflectionClass($class, false);

        return $reflection instanceof ReflectionClass && ($reflection->getName() === $type || $reflection->isSubclassOf($type));
    }
}
