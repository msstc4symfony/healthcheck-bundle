<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;

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
        try {
            $this->store->save($key);
        } finally {
            $this->store->delete($key);
        }
    }

    protected function label(): string
    {
        return sprintf('Lock store (%s)', $this->name);
    }
}
