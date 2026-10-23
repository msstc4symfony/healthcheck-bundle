<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Run\ProbeBudget;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Run\SharedProbes;

final readonly class Context
{
    /**
     * One Context per run: the run state ($budget, $sharedProbes) must never be reused across runs.
     *
     * @param array<string, mixed> $options
     */
    public function __construct(
        public CheckTypeEnum $type,
        public array $options = [],
        /** @internal run state, not for callers */
        public ?ProbeBudget $budget = null,
        /** @internal run state, not for callers */
        public SharedProbes $sharedProbes = new SharedProbes(),
    ) {
    }
}
