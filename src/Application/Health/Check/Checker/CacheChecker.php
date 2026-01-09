<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Throwable;

#[Exclude]
final class CacheChecker implements CheckInterface
{
    private const CACHE_SERVICE_CELL = '__healthcheck';

    public function __construct(
        private readonly AdapterInterface $connection,
        private readonly string $id,
        private readonly ?string $parentName = null,
    ) {
    }

    public function isSupport(Context $context): bool
    {
        return $context->type === CheckTypeEnum::READINESS;
    }

    public function check(CheckResult $result, Context $context): CheckResult
    {
        try {
            if (
                !($this->connection instanceof NullAdapter)
                && $this->isAllowedAPCuOrNotAPCu()
            ) {
                $item = $this->connection->getItem(self::CACHE_SERVICE_CELL);
                $item->set(time());

                $this->processResult($result, $this->connection->save($item));

                return $result;
            }
        } catch (Throwable $e) {
            if ($this->parentName !== null) {
                $result->errors[] = sprintf(
                    'Cache / %s (%s : %s) connection failed (%s)',
                    $this->connection::class,
                    $this->parentName,
                    $this->id,
                    $e->getMessage(),
                );

                return $result;
            }

            $result->errors[] = sprintf(
                'Cache / %s (%s) connection failed (%s)',
                $this->connection::class,
                $this->id,
                $e->getMessage(),
            );

            return $result;
        }

        $result->messages[] = sprintf(
            'Cache (%s : %s) connection passed',
            $this->connection::class,
            $this->id,
        );

        return $result;
    }

    private function processResult(CheckResult $result, bool $success): void
    {
        if ($this->parentName !== null) {
            if ($success) {
                $result->messages[] = sprintf('Cache (%s / %s : %s) connection passed', $this->connection::class, $this->parentName, $this->id);

                return;
            }

            $result->errors[] = sprintf('Cache (%s / %s : %s) connection failed', $this->connection::class, $this->parentName, $this->id);

            return;
        }

        if ($success) {
            $result->messages[] = sprintf('Cache (%s : %s) connection passed', $this->connection::class, $this->id);

            return;
        }

        $result->errors[] = sprintf('Cache (%s : %s) connection failed', $this->connection::class, $this->id);
    }

    private function isAllowedAPCuOrNotAPCu(): bool
    {
        return !($this->connection instanceof ApcuAdapter)
            || PHP_SAPI !== 'cli'
            || filter_var(ini_get('apc.enable_cli'), FILTER_VALIDATE_BOOLEAN);
    }
}
