<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\DependencyInjection;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\ServiceClass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Traversable;

#[CoversClass(ServiceClass::class)]
final class ServiceClassTest extends TestCase
{
    public function testMatchesTheTypeItsSubclassesAndImplementations(): void
    {
        $container = new ContainerBuilder();

        self::assertTrue(ServiceClass::is($container, new Definition(ArrayIterator::class), ArrayIterator::class));
        self::assertTrue(ServiceClass::is($container, new Definition(ArrayIterator::class), Countable::class));
        self::assertFalse(ServiceClass::is($container, new Definition(ArrayIterator::class), IteratorAggregate::class));
        self::assertFalse(ServiceClass::is($container, new Definition(), Traversable::class));
    }

    public function testResolvesTheClassThroughTheParentChain(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('prototype', new Definition(ArrayIterator::class)->setAbstract(true));
        $container->setDefinition('middle', new ChildDefinition('prototype'));

        $leaf = new ChildDefinition('middle');

        self::assertTrue(ServiceClass::is($container, $leaf, Countable::class));
    }

    public function testChildClassOverridesTheParentClass(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('prototype', new Definition(ArrayIterator::class));

        $child = new ChildDefinition('prototype');
        $child->setClass(Traversable::class);

        self::assertFalse(ServiceClass::is($container, $child, Countable::class));
    }

    public function testChildOfMissingOrCyclicParentHasNoClass(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('a', new ChildDefinition('b'));
        $container->setDefinition('b', new ChildDefinition('a'));

        self::assertFalse(ServiceClass::is($container, new ChildDefinition('missing'), Traversable::class));
        self::assertFalse(ServiceClass::is($container, $container->getDefinition('a'), Traversable::class));
    }

    public function testChildWhoseParentAliasPointsToAMissingServiceHasNoClass(): void
    {
        $container = new ContainerBuilder();
        $container->setAlias('prototype.alias', new Alias('missing'));

        self::assertFalse(ServiceClass::is($container, new ChildDefinition('prototype.alias'), Traversable::class));
    }

    public function testResolvesTheClassThroughAParentAlias(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('prototype', new Definition(ArrayIterator::class));
        $container->setAlias('prototype.alias', new Alias('prototype'));

        self::assertTrue(ServiceClass::is($container, new ChildDefinition('prototype.alias'), Countable::class));
    }

    public function testResolvesClassesGivenAsParameters(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('app.iterator_class', ArrayIterator::class);

        self::assertTrue(ServiceClass::is($container, new Definition('%app.iterator_class%'), Traversable::class));
    }

    public function testWithoutTheTypeOnlyTheExactClassNameMatches(): void
    {
        $container = new ContainerBuilder();

        self::assertTrue(ServiceClass::is($container, new Definition('\Not\Installed\Producer'), 'Not\Installed\Producer'));
        self::assertFalse(ServiceClass::is($container, new Definition(ArrayIterator::class), 'Not\Installed\Producer'));
    }

    /**
     * Applications routinely contain such services (e.g. security-core's UserPasswordValidator
     * without symfony/validator); is_subclass_of() on them is a fatal error.
     */
    public function testSurvivesClassesWhoseParentIsNotInstalled(): void
    {
        $class = 'Msstc4Symfony\HealthCheckBundle\Test\Generated\OrphanService';
        // Generated at run time: a source file extending a missing class would fail static analysis.
        $file = sys_get_temp_dir() . '/msstc4symfony-healthcheck-orphan-' . getmypid() . '.php';
        file_put_contents($file, "<?php\nnamespace Msstc4Symfony\\HealthCheckBundle\\Test\\Generated;\nfinal class OrphanService extends \\Not\\Installed\\BaseService {}\n");
        $autoload = static function (string $name) use ($class, $file): void {
            if ($name === $class) {
                require $file;
            }
        };
        spl_autoload_register($autoload);

        try {
            self::assertFalse(ServiceClass::is(new ContainerBuilder(), new Definition($class), Countable::class));
        } finally {
            spl_autoload_unregister($autoload);
            unlink($file);
        }
    }
}
