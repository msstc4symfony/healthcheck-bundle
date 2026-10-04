<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\DependencyInjection\Detector;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\LockStoreChecker;
use Msstc4Symfony\HealthCheckBundle\DependencyInjection\Detector\LockStoreDetector;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Lock\Store\SemaphoreStore;
use Symfony\Component\Lock\Store\StoreFactory;

final class LockStoreDetectorTest extends TestCase
{
    #[RequiresMethod(PersistingStoreInterface::class, 'save')]
    public function testDetectYieldsCheckerForPersistingStoreSubclass(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('lock.default.store', new Definition(InMemoryStore::class));

        $detected = iterator_to_array(new LockStoreDetector()->detect($container));

        $checker = $detected['healthcheck.checker.lock.default.store'];
        self::assertSame(LockStoreChecker::class, $checker->getClass());
        self::assertEquals(new Reference('lock.default.store'), $checker->getArgument(0));
        self::assertSame('lock.default.store', $checker->getArgument(1));
    }

    #[RequiresMethod(PersistingStoreInterface::class, 'save')]
    public function testDetectMatchesMultipleStoreImplementations(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('lock.in_memory', new Definition(InMemoryStore::class));
        $container->setDefinition('lock.flock', new Definition(FlockStore::class));

        $detected = iterator_to_array(new LockStoreDetector()->detect($container));

        self::assertArrayHasKey('healthcheck.checker.lock.in_memory', $detected);
        self::assertArrayHasKey('healthcheck.checker.lock.flock', $detected);
    }

    public function testDetectMatchesExactInterfaceClass(): void
    {
        // A service explicitly typed as the interface itself must still be detected.
        $container = new ContainerBuilder();
        $container->setDefinition('lock.iface', new Definition(PersistingStoreInterface::class));

        $detected = iterator_to_array(new LockStoreDetector()->detect($container));

        self::assertArrayHasKey('healthcheck.checker.lock.iface', $detected);
    }

    /**
     * FrameworkBundle 8.1 predefines ".lock.flock.store" and ".lock.semaphore.store" in lock.php and
     * tags a store "lock.store" only when a resource is configured with it; DSN stores become
     * ".lock.<resource>.store.<hash>" factory definitions tagged the same way.
     */
    #[RequiresMethod(PersistingStoreInterface::class, 'save')]
    public function testDetectSkipsFrameworkPredefinedStoresThatNoResourceUses(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('.lock.flock.store', new Definition(FlockStore::class));
        $container->setDefinition('.lock.semaphore.store', new Definition(SemaphoreStore::class));
        $container->setDefinition('.lock.default.store.abc123', new Definition(PersistingStoreInterface::class)
            ->setFactory([StoreFactory::class, 'createStore'])
            ->setArguments(['redis://redis:6379/0'])
            ->addTag('lock.store'));

        $detected = iterator_to_array(new LockStoreDetector()->detect($container));

        self::assertSame(['healthcheck.checker..lock.default.store.abc123'], array_keys($detected));
    }

    #[RequiresMethod(PersistingStoreInterface::class, 'save')]
    public function testDetectProbesPredefinedStoreOnceAResourceUsesIt(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('.lock.flock.store', new Definition(FlockStore::class));
        $container->setDefinition('.lock.semaphore.store', new Definition(SemaphoreStore::class)->addTag('lock.store'));

        $detected = iterator_to_array(new LockStoreDetector()->detect($container));

        self::assertSame(['healthcheck.checker..lock.semaphore.store'], array_keys($detected));
    }

    public function testNamesFrameworkStoreCheckerByItsLockResourceWithoutTheStoreHash(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('.lock.default.store.abc123', $this->frameworkStore());
        $container->setDefinition('lock.default.factory', $this->resourceFactory('.lock.default.store.abc123'));

        $detected = iterator_to_array(new LockStoreDetector()->detect($container));

        self::assertSame(['healthcheck.checker.lock.default'], array_keys($detected));
        self::assertEquals(new Reference('.lock.default.store.abc123'), $detected['healthcheck.checker.lock.default']->getArgument(0));
        self::assertSame('lock.default', $detected['healthcheck.checker.lock.default']->getArgument(1));
    }

    public function testNamesAStoreSharedByResourcesAfterEveryResourceInSortedOrder(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('.lock.default.store.abc123', $this->frameworkStore());
        $container->setDefinition('lock.reports.factory', $this->resourceFactory('.lock.default.store.abc123'));
        $container->setDefinition('lock.default.factory', $this->resourceFactory('.lock.default.store.abc123'));

        $detected = iterator_to_array(new LockStoreDetector()->detect($container));

        self::assertSame(['healthcheck.checker.lock.default+lock.reports'], array_keys($detected));
        self::assertSame('lock.default, lock.reports', $detected['healthcheck.checker.lock.default+lock.reports']->getArgument(1));
    }

    public function testLabelsStoresOfACombinedResourceByPosition(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('.lock.invoice.store.aaa', $this->frameworkStore());
        $container->setDefinition('.lock.invoice.store.bbb', $this->frameworkStore());
        $container->setDefinition('.lock.invoice.store.ccc', new ChildDefinition('lock.store.combined.abstract')
            ->replaceArgument(0, [new Reference('.lock.invoice.store.aaa'), new Reference('.lock.invoice.store.bbb')]));
        $container->setDefinition('lock.invoice.factory', $this->resourceFactory('.lock.invoice.store.ccc'));

        $detected = iterator_to_array(new LockStoreDetector()->detect($container));

        self::assertSame(['healthcheck.checker.lock.invoice[0]', 'healthcheck.checker.lock.invoice[1]'], array_keys($detected), 'the untagged combined store itself is not probed');
        self::assertEquals(new Reference('.lock.invoice.store.aaa'), $detected['healthcheck.checker.lock.invoice[0]']->getArgument(0));
        self::assertSame('lock.invoice[0]', $detected['healthcheck.checker.lock.invoice[0]']->getArgument(1));
        self::assertSame('lock.invoice[1]', $detected['healthcheck.checker.lock.invoice[1]']->getArgument(1));
    }

    #[RequiresMethod(PersistingStoreInterface::class, 'save')]
    public function testLabelsAPredefinedStoreSharedByResourcesWithEveryResource(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('.lock.flock.store', new Definition(FlockStore::class)->addTag('lock.store'));
        $container->setDefinition('lock.default.factory', $this->resourceFactory('.lock.flock.store'));
        $container->setDefinition('lock.reports.factory', $this->resourceFactory('.lock.flock.store'));

        $detected = iterator_to_array(new LockStoreDetector()->detect($container));

        self::assertSame(['healthcheck.checker.lock.default+lock.reports'], array_keys($detected));
        self::assertEquals(new Reference('.lock.flock.store'), $detected['healthcheck.checker.lock.default+lock.reports']->getArgument(0));
        self::assertSame('lock.default, lock.reports', $detected['healthcheck.checker.lock.default+lock.reports']->getArgument(1));
    }

    public function testReadsResourceFactoriesDeclaredWithPositionalArgumentsAndIgnoresLookalikeIds(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('.lock.default.store.abc123', $this->frameworkStore());
        $container->setDefinition('lock.default.factory', new ChildDefinition('lock.factory.abstract')->setArguments([new Reference('.lock.default.store.abc123')]));
        $container->setDefinition('app.lock.other.factory', $this->resourceFactory('.lock.default.store.abc123'));
        $container->setDefinition('lock.other.factory.decorated', $this->resourceFactory('.lock.default.store.abc123'));
        $container->setDefinition('lock.plain.factory', new ChildDefinition('app.factory')->replaceArgument(0, new Reference('.lock.default.store.abc123')));

        $detected = iterator_to_array(new LockStoreDetector()->detect($container));

        self::assertSame(['healthcheck.checker.lock.default'], array_keys($detected));
    }

    public function testLabelsByPositionInTheCombinedStoreArgumentAndSkipsANonCombinedParent(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('.lock.invoice.store.aaa', $this->frameworkStore());
        $container->setDefinition('.lock.invoice.store.combined', new ChildDefinition('lock.store.combined.abstract')
            ->replaceArgument(0, [5 => new Reference('.lock.invoice.store.aaa')]));
        $container->setDefinition('lock.invoice.factory', $this->resourceFactory('.lock.invoice.store.combined'));
        $container->setDefinition('.lock.other.store.ccc', new ChildDefinition('app.store.template')->replaceArgument(0, [new Reference('.lock.invoice.store.aaa')])->addTag('lock.store'));
        $container->setDefinition('lock.other.factory', $this->resourceFactory('.lock.other.store.ccc'));

        $detected = iterator_to_array(new LockStoreDetector()->detect($container));

        self::assertSame('lock.invoice[0]', $detected['healthcheck.checker.lock.invoice[0]']->getArgument(1));
    }

    public function testFallsBackToTheServiceIdForAStoreNoResourceFactoryReferences(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('.lock.default.store.abc123', $this->frameworkStore());
        $container->setDefinition('lock.default.factory', new Definition(stdClass::class));

        $detected = iterator_to_array(new LockStoreDetector()->detect($container));

        self::assertSame('.lock.default.store.abc123', $detected['healthcheck.checker..lock.default.store.abc123']->getArgument(1));
    }

    public function testDetectSkipsHiddenStoreWithoutLockStoreTag(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('.some.internal.store', new Definition(PersistingStoreInterface::class));

        self::assertSame([], iterator_to_array(new LockStoreDetector()->detect($container)));
    }

    public function testDetectIgnoresUnrelatedAndClasslessDefinitions(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.other', new Definition(stdClass::class));
        $container->setDefinition('app.classless', new Definition());

        self::assertSame([], iterator_to_array(new LockStoreDetector()->detect($container)));
    }

    private function frameworkStore(): Definition
    {
        return new Definition(PersistingStoreInterface::class)
            ->setFactory([StoreFactory::class, 'createStore'])
            ->setArguments(['redis://redis:6379/0'])
            ->addTag('lock.store')
        ;
    }

    private function resourceFactory(string $storeId): ChildDefinition
    {
        return new ChildDefinition('lock.factory.abstract')->replaceArgument(0, new Reference($storeId));
    }
}
