<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;

final readonly class Context
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public CheckTypeEnum $type,
        public array $options = [],
    ) {
    }
}
