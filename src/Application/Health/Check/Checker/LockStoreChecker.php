<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

use Override;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Throwable;

#[Exclude]
final readonly class LockStoreChecker extends AbstractReadinessChecker
{
    public function __construct(
        private PersistingStoreInterface $store,
        private string $name,
    ) {
    }

    #[Override]
    protected function doCheck(): ?string
    {
        $key = new Key(CheckInterface::PROBE_KEY);

        // Let save() throw naturally — its exception is the actual probe failure.
        $this->store->save($key);

        // Best-effort cleanup; never mask the save() outcome with a delete() failure.
        try {
            $this->store->delete($key);
        } catch (Throwable) {
            // ignored
        }

        return null;
    }

    #[Override]
    protected function label(): string
    {
        return sprintf('Lock store (%s)', $this->name);
    }
}
