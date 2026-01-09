<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker;

use Elastica\Client;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Throwable;

#[Exclude]
final class ElasticaConnectionChecker implements CheckInterface
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
            $connections = $this->connection->getConfig('connections');
            if (
                count($connections) === 0
                || (count($connections) === 1 && is_array($connections[0]) && ($connections[0]['host'] ?? null) === 'localhost')
            ) {
                $result->messages[] = sprintf('Elastica connection (%s) cannot be checked: connections list is empty', $this->name);

                return $result;
            }

            /** @psalm-suppress UndefinedClass */
            $status = $this->connection->getCluster()->getHealth()->getStatus();

            /** @psalm-suppress UndefinedClass */
            $result->messages[] = sprintf(
                'Elastica connection (%s) passed. Cluster status: %s',
                $this->name,
                $status,
            );
        } catch (Throwable $e) {
            $result->errors[] = sprintf('Elastica connection (%s) failed. Reason: %s', $this->name, $e->getMessage());
        }

        return $result;
    }
}
