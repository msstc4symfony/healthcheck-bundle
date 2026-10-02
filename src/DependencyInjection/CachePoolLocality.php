<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\DependencyInjection;

use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tells cache pools kept in process memory or on the local disk apart from pools that depend on
 * infrastructure. Unknown adapters count as infrastructure: a redundant probe beats a missed one.
 *
 * @internal
 */
final class CachePoolLocality
{
    private const array LOCAL_ADAPTERS = [
        ApcuAdapter::class,
        ArrayAdapter::class,
        FilesystemAdapter::class,
        FilesystemTagAwareAdapter::class,
        NullAdapter::class,
        PhpArrayAdapter::class,
        PhpFilesAdapter::class,
    ];

    // FrameworkBundle's "cache.adapter.system" (APCu + PHP files) is declared as AdapterInterface
    // built by this factory, so its class alone does not reveal the adapter.
    private const string SYSTEM_CACHE_FACTORY_METHOD = 'createSystemCache';

    public static function isLocal(ContainerBuilder $container, Definition $pool): bool
    {
        $class = null;
        $factory = null;
        $arguments = [];
        // A child's class and factory override its parent's, as in the DI component itself.
        for ($definition = $pool;; $definition = $container->findDefinition($definition->getParent())) {
            $class ??= $definition->getClass();
            $factory ??= $definition->getFactory();
            $arguments += $definition->getArguments();
            if (!$definition instanceof ChildDefinition) {
                break;
            }
        }

        if (is_array($factory) && ($factory[1] ?? null) === self::SYSTEM_CACHE_FACTORY_METHOD) {
            return true;
        }

        if ($class === null) {
            return false;
        }

        $typed = new Definition($class);
        if (ServiceClass::is($container, $typed, ChainAdapter::class)) {
            $chained = $arguments['index_0'] ?? $arguments[0] ?? null;

            return is_array($chained) && self::isLocalChain($container, $chained);
        }

        return array_any(
            self::LOCAL_ADAPTERS,
            static fn (string $local): bool => ServiceClass::is($container, $typed, $local),
        );
    }

    /**
     * Before CachePoolPass the chained adapters are service ids; after it, inline child definitions.
     *
     * @param array<array-key, mixed> $adapters raw ChainAdapter constructor argument
     */
    private static function isLocalChain(ContainerBuilder $container, array $adapters): bool
    {
        if ($adapters === []) {
            return false;
        }

        foreach ($adapters as $adapter) {
            $definition = match (true) {
                $adapter instanceof Definition => $adapter,
                (is_string($adapter) || $adapter instanceof Reference) && $container->has((string) $adapter) => $container->findDefinition((string) $adapter),
                default => null,
            };
            if (!$definition instanceof Definition || !self::isLocal($container, $definition)) {
                return false;
            }
        }

        return true;
    }
}
