<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

use Override;
use RuntimeException;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class CacheChecker extends AbstractReadinessChecker
{
    public function __construct(
        private AdapterInterface $connection,
        private string $id,
        private ?string $parentName,
    ) {
    }

    #[Override]
    protected function doCheck(): ?string
    {
        $item = $this->connection->getItem(self::PROBE_KEY);
        $item->set(time());

        if (!$this->connection->save($item)) {
            throw new RuntimeException('save() returned false');
        }

        return null;
    }

    #[Override]
    protected function label(): string
    {
        // The adapter implementation class is intentionally NOT included — exposing it leaks
        // Symfony-internal type names into the probe HTTP/CLI output. The parent service id
        // already identifies the underlying adapter type in the standard Symfony cache wiring.
        return $this->parentName !== null
            ? sprintf('Cache (%s : %s) connection', $this->parentName, $this->id)
            : sprintf('Cache (%s) connection', $this->id);
    }

    #[Override]
    protected function skipReason(): ?string
    {
        if ($this->connection instanceof NullAdapter) {
            return 'NullAdapter';
        }

        if ($this->connection instanceof ApcuAdapter && PHP_SAPI === 'cli' && !filter_var(ini_get('apc.enable_cli'), FILTER_VALIDATE_BOOLEAN)) {
            return 'APCu in CLI without apc.enable_cli';
        }

        return null;
    }
}
