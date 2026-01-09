<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Memcache;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Throwable;

#[Exclude]
final class MemcacheChecker implements CheckInterface
{
    private const CACHE_SERVICE_CELL = '__healthcheck';

    public function __construct(
        private readonly Memcache $connection,
    ) {
    }

    public function isSupport(Context $context): bool
    {
        return $context->type === CheckTypeEnum::READINESS;
    }

    public function check(CheckResult $result, Context $context): CheckResult
    {
        try {
            if ($this->connection->set(self::CACHE_SERVICE_CELL, time(), 0, 1)) {
                $result->messages[] = 'Memcache connection passed';
            } else {
                $result->errors[] = 'Memcache connection failed';
            }
        } catch (Throwable $e) {
            $result->errors[] = 'Memcache connection failed: ' . $e->getMessage();
        }

        return $result;
    }
}
