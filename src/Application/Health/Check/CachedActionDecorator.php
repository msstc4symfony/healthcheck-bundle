<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Request;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Response;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Wraps any ActionInterface in a PSR-6 cache layer keyed by request type+options.
 * Targets k8s probe scenarios where the same readiness check is hit every 5-10s.
 *
 * Only successful responses are cached. A transient failure (100ms blip) won't trap the
 * service at "down" for the configured TTL — the next probe re-runs the chain and recovers
 * as soon as the underlying issue clears.
 */
#[Exclude]
final readonly class CachedActionDecorator implements ActionInterface
{
    public function __construct(
        private ActionInterface $inner,
        private CacheItemPoolInterface $cache,
        private int $ttlSeconds,
    ) {
    }

    public function run(Request $request): Response
    {
        $key = $this->buildKey($request);
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            $cached = $item->get();
            if ($cached instanceof Response) {
                return $cached;
            }
            // Cache pollution (foreign value under our key). Evict eagerly so a failing run
            // does not leave the polluted value to be re-served by the next probe.
            $this->cache->deleteItem($key);
            $item = $this->cache->getItem($key);
        }

        $response = $this->inner->run($request);

        if ($response->success) {
            $item->set($response);
            $item->expiresAfter($this->ttlSeconds);
            $this->cache->save($item);
        }

        return $response;
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $sorted = array_map(self::canonicalize(...), $value);
        ksort($sorted, SORT_STRING);

        return $sorted;
    }

    private function buildKey(Request $request): string
    {
        $optionsHash = hash('sha256', json_encode(self::canonicalize($request->options), JSON_THROW_ON_ERROR));

        return sprintf('maxshamaev_healthcheck.%s.%s', $request->type->value, $optionsHash);
    }
}
