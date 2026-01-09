<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Redis;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final class RedisChecker implements CheckInterface
{
    private const CACHE_SERVICE_CELL = '__healthcheck';

    public function __construct(
        private readonly Redis $connection,
    ) {
    }

    public function isSupport(Context $context): bool
    {
        return $context->type === CheckTypeEnum::READINESS;
    }

    public function check(CheckResult $result, Context $context): CheckResult
    {
        if ($this->connection->set(self::CACHE_SERVICE_CELL, (string) time(), 1)) {
            $result->messages[] = 'Redis connection passed';
        } else {
            $result->errors[] = 'Redis connection failed';
        }

        return $result;
    }
}
