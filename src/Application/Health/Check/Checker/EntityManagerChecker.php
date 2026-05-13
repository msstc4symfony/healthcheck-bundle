<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class EntityManagerChecker extends AbstractReadinessChecker
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $name,
    ) {
    }

    protected function doCheck(): void
    {
        $connection = $this->entityManager->getConnection();
        if (!$connection->isConnected()) {
            $connection->getServerVersion();
        }

        // Force loading of all metadata — broken mappings throw MappingException here.
        $this->entityManager->getMetadataFactory()->getAllMetadata();
    }

    protected function label(): string
    {
        return sprintf('EntityManager (%s)', $this->name);
    }
}
