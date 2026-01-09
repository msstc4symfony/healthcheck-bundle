<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PhpAmqpLib\Connection\AbstractConnection;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Throwable;

#[Exclude]
final class RabbitmqChecker implements CheckInterface
{
    public function __construct(
        private readonly AbstractConnection $connection,
    ) {
    }

    public function isSupport(Context $context): bool
    {
        return $context->type === CheckTypeEnum::READINESS;
    }

    public function check(CheckResult $result, Context $context): CheckResult
    {
        try {
            $this->connection->reconnect();
            if (!$this->connection->isConnected()) {
                $result->errors[] = 'RabbitMQ connection failed';

                return $result;
            }

            $result->messages[] = 'RabbitMQ connection passed';
        } catch (Throwable $e) {
            $result->errors[] = sprintf('RabbitMQ connection failed (%s)', $e->getMessage());
        }

        return $result;
    }
}
