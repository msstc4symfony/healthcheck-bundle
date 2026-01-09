<?php declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
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
