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
final readonly class CacheChecker implements CheckInterface
{
    public function __construct(
        private AdapterInterface $connection,
        private string $id,
        private ?string $parentName = null,
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
                && $this->canProbe()
            ) {
                $item = $this->connection->getItem(self::PROBE_KEY);
                $item->set(time());

                if ($this->connection->save($item)) {
                    $result->addMessage(sprintf('%s passed', $this->buildLabel()));
                } else {
                    $result->addError(sprintf('%s failed', $this->buildLabel()));
                }

                return $result;
            }
        } catch (Throwable $e) {
            $result->addError(sprintf('%s failed (%s)', $this->buildLabel(), $e->getMessage()));

            return $result;
        }

        $result->addMessage(sprintf('%s passed', $this->buildLabel()));

        return $result;
    }

    private function buildLabel(): string
    {
        return $this->parentName !== null
            ? sprintf('Cache (%s / %s : %s) connection', $this->connection::class, $this->parentName, $this->id)
            : sprintf('Cache (%s : %s) connection', $this->connection::class, $this->id);
    }

    private function canProbe(): bool
    {
        return !($this->connection instanceof ApcuAdapter)
            || PHP_SAPI !== 'cli'
            || filter_var(ini_get('apc.enable_cli'), FILTER_VALIDATE_BOOLEAN);
    }
}
