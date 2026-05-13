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
        $item = $this->cache->getItem($this->buildKey($request));
        if ($item->isHit()) {
            /** @var Response $cached */
            $cached = $item->get();

            return $cached;
        }

        $response = $this->inner->run($request);
        $item->set($response);
        $item->expiresAfter($this->ttlSeconds);

        $this->cache->save($item);

        return $response;
    }

    private function buildKey(Request $request): string
    {
        $optionsHash = hash('sha256', json_encode($request->options, JSON_THROW_ON_ERROR));

        return sprintf('maxshamaev_healthcheck.%s.%s', $request->type->value, $optionsHash);
    }
}
