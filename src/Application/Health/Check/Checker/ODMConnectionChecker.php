<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

use Doctrine\ODM\MongoDB\DocumentManager;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class ODMConnectionChecker extends AbstractReadinessChecker
{
    private const string DEFAULT_PING_DATABASE = 'admin';

    public function __construct(
        private DocumentManager $documentManager,
        private string $name,
    ) {
    }

    #[Override]
    protected function doCheck(): void
    {
        $defaultDb = $this->documentManager->getConfiguration()->getDefaultDB() ?? self::DEFAULT_PING_DATABASE;
        $this->documentManager->getClient()->selectDatabase($defaultDb)->command(['ping' => 1]);
    }

    #[Override]
    protected function label(): string
    {
        return sprintf('ODM connection (%s)', $this->name);
    }
}
