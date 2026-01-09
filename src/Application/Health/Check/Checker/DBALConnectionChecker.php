<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker;

use Doctrine\DBAL\Connection;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Throwable;

#[Exclude]
final class DBALConnectionChecker implements CheckInterface
{
    public function __construct(
        private readonly Connection $connection,
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
            if (!$this->connection->isConnected() && !$this->connection->connect()) {
                $result->errors[] = sprintf('DB connection (%s) failed', $this->name);

                return $result;
            }

            $result->messages[] = sprintf('DB connection (%s) passed', $this->name);
        } catch (Throwable $e) {
            $result->errors[] = sprintf('DB connection (%s) failed. Reason: %s', $this->name, $e->getMessage());
        }

        return $result;
    }
}
