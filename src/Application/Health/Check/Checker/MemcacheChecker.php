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
final readonly class MemcacheChecker implements CheckInterface
{
    private const string CACHE_SERVICE_CELL = '__healthcheck';

    public function __construct(
        private Memcache $connection,
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
                $result->addMessage('Memcache connection passed');
            } else {
                $result->addError('Memcache connection failed');
            }
        } catch (Throwable $e) {
            $result->addError(sprintf('Memcache connection failed (%s)', $e->getMessage()));
        }

        return $result;
    }
}
