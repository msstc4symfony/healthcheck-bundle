<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Predis\Client;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Throwable;

#[Exclude]
final class PredisChecker implements CheckInterface
{
    public function __construct(
        private readonly Client $connection,
    ) {
    }

    public function isSupport(Context $context): bool
    {
        return $context->type === CheckTypeEnum::READINESS;
    }

    public function check(CheckResult $result, Context $context): CheckResult
    {
        try {
            if (!$this->connection->isConnected()) {
                $this->connection->connect();
            }

            if (!$this->connection->isConnected()) {
                $result->errors[] = 'Redis connection failed';

                return $result;
            }

            $result->messages[] = 'Redis connection passed';
        } catch (Throwable $e) {
            $result->errors[] = sprintf('Redis connection failed (%s)', $e->getMessage());
        }

        return $result;
    }
}
