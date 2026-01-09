<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Memcached;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Throwable;

#[Exclude]
final class MemcachedChecker implements CheckInterface
{
    private const CACHE_SERVICE_CELL = '__healthcheck';

    public function __construct(
        private readonly Memcached $connection,
    ) {
    }

    public function isSupport(Context $context): bool
    {
        return $context->type === CheckTypeEnum::READINESS;
    }

    public function check(CheckResult $result, Context $context): CheckResult
    {
        try {
            if ($this->connection->set(self::CACHE_SERVICE_CELL, time(), 1)) {
                $result->messages[] = 'Memcached connection passed';
            } else {
                $result->errors[] = 'Memcached connection failed';
            }
        } catch (Throwable $e) {
            $result->errors[] = 'Memcached connection failed: ' . $e->getMessage();
        }

        return $result;
    }
}
