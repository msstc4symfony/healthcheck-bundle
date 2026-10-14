<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Cached serialized by CachedActionDecorator: a change to its properties needs a new KEY_PREFIX there.
 */
#[Exclude]
final readonly class Response
{
    public bool $success;

    /**
     * @param string[] $errors
     * @param string[] $messages
     * @param string[] $warnings
     */
    public function __construct(
        public array $errors,
        public array $messages,
        public array $warnings,
    ) {
        $this->success = $errors === [];
    }
}
