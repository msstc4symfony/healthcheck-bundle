<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final class Response
{
    public bool $success {
        get => $this->errors === [];
    }

    /**
     * @param string[] $errors
     * @param string[] $messages
     * @param string[] $warnings
     */
    public function __construct(
        public readonly array $errors,
        public readonly array $messages,
        public readonly array $warnings,
    ) {
    }
}
