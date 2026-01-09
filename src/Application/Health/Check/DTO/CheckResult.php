<?php declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO;

final class CheckResult
{
    /**
     * @var string[]
     */
    public array $messages = [];

    /**
     * @var string[]
     */
    public array $errors = [];
}
