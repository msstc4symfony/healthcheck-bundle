<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO;

/**
 * Immutable probe-target description for HttpClientChecker — collapses the four
 * detector-side configuration values (url + method + expected codes + timeout) into
 * a single VO that travels through the DI wiring as one constructor argument.
 */
final readonly class HttpProbeTarget
{
    /**
     * @param list<int> $expectedStatusCodes
     */
    public function __construct(
        public string $url,
        public string $method,
        public array $expectedStatusCodes,
        public int $timeoutSeconds,
    ) {
    }
}
