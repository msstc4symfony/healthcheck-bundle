<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Probes that the ORM's underlying DB connection is reachable.
 *
 * We deliberately do NOT walk ClassMetadataFactory::getAllMetadata() here: on apps with hundreds
 * of entities that scans every mapping source on each probe (k8s polls every 5-10s), making
 * the readiness endpoint itself a load source. Broken mappings surface on the first business
 * request anyway.
 */
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
        $this->entityManager->getConnection()->executeQuery('SELECT 1');
    }

    protected function label(): string
    {
        return sprintf('EntityManager (%s)', $this->name);
    }
}
