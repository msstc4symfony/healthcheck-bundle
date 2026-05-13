<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Throwable;

#[Exclude]
final readonly class LockStoreChecker extends AbstractReadinessChecker
{
    private const string PROBE_RESOURCE = '__healthcheck';

    public function __construct(
        private PersistingStoreInterface $store,
        private string $name,
    ) {
    }

    protected function doCheck(): void
    {
        $key = new Key(self::PROBE_RESOURCE);

        // Let save() throw naturally — its exception is the actual probe failure.
        $this->store->save($key);

        // Best-effort cleanup; never mask the save() outcome with a delete() failure.
        try {
            $this->store->delete($key);
        } catch (Throwable) {
            // ignored
        }
    }

    protected function label(): string
    {
        return sprintf('Lock store (%s)', $this->name);
    }
}
