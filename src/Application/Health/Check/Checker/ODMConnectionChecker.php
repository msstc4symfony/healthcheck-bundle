<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker;

use Doctrine\ODM\MongoDB\DocumentManager;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Context;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Throwable;

#[Exclude]
final class ODMConnectionChecker implements CheckInterface
{
    public function __construct(
        private readonly DocumentManager $documentManager,
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
            $defaultDb = $this->documentManager->getConfiguration()->getDefaultDB() ?? 'admin';
            $this->documentManager->getClient()->selectDatabase($defaultDb)->command(['ping' => 1]);

            $result->addMessage(sprintf('ODM connection (%s) passed', $this->name));
        } catch (Throwable $e) {
            $result->addError(sprintf('ODM connection (%s) failed. Reason: %s', $this->name, $e->getMessage()));
        }

        return $result;
    }
}
