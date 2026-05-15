<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker;

use Elastica\Client;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Throwable;

#[Exclude]
final readonly class ElasticaConnectionChecker implements CheckInterface
{
    public function __construct(
        private Client $connection,
        private string $name,
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
                $result->addMessage(sprintf('Elastica connection (%s) cannot be checked: connections list is empty', $this->name));

                return $result;
            }

            $status = $this->connection->getCluster()->getHealth()->getStatus();

            $result->addMessage(sprintf(
                'Elastica connection (%s) passed. Cluster status: %s',
                $this->name,
                $status,
            ));
        } catch (Throwable $e) {
            $result->addError(sprintf('Elastica connection (%s) failed. Reason: %s', $this->name, $e->getMessage()));
        }

        return $result;
    }
}
