<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO;

use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;

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
