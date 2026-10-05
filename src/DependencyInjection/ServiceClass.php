<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\DependencyInjection;

use ReflectionClass;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

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
        $class = $container->getParameterBag()->resolveValue(self::declaredClass($container, $definition));
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

    /**
     * Passes run before ResolveChildDefinitionsPass, so a child (e.g. FOSElasticaBundle's clients)
     * has no class of its own and inherits it from the parent chain.
     */
    private static function declaredClass(ContainerBuilder $container, Definition $definition): ?string
    {
        $seen = [];
        while ($definition->getClass() === null && $definition instanceof ChildDefinition) {
            $parent = $definition->getParent();
            if (isset($seen[$parent]) || !$container->has($parent)) {
                return null;
            }
            $seen[$parent] = true;
            try {
                $definition = $container->findDefinition($parent);
            } catch (ServiceNotFoundException) {
                return null;
            }
        }

        return $definition->getClass();
    }
}
