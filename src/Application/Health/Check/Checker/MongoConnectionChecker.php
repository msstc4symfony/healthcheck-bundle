<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use MongoDB\Client;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Throwable;

#[Exclude]
final class MongoConnectionChecker implements CheckInterface
{
    public function __construct(
        private readonly Client $connection,
        private readonly string $name,
    ) {
    }

    public function isSupport(Context $context): bool
    {
        return $context->type === CheckTypeEnum::READINESS;
    }

    public function check(CheckResult $result, Context $context): CheckResult
    {
        try {
            $this->connection->listDatabaseNames();

            $result->messages[] = sprintf('Mongo connection (%s) passed', $this->name);
        } catch (Throwable $e) {
            $result->errors[] = sprintf('Mongo connection (%s) failed. Reason: %s', $this->name, $e->getMessage());
        }

        return $result;
    }
}
