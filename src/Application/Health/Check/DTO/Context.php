<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;

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
