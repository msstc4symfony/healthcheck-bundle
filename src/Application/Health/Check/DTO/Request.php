<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final class Request
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public private(set) CheckTypeEnum $type = CheckTypeEnum::LIVELINESS,
        public private(set) array $options = [],
    ) {
    }
}
