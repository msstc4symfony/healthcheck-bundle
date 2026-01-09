<?php declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final class Response
{
    /**
     * @param string[] $errors
     * @param string[] $messages
     */
    public function __construct(
        public readonly bool $success,
        public readonly array $errors,
        public readonly array $messages,
    ) {
    }
}
