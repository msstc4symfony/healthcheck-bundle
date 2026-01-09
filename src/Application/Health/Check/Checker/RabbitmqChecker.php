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
final readonly class RabbitmqChecker implements CheckInterface
{
    public function __construct(
        private AbstractConnection $connection,
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
                $result->addError('RabbitMQ connection failed');

                return $result;
            }

            $result->addMessage('RabbitMQ connection passed');
        } catch (Throwable $e) {
            $result->addError(sprintf('RabbitMQ connection failed (%s)', $e->getMessage()));
        }

        return $result;
    }
}
