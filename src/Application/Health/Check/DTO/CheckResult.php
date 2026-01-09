<?php declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO;

final class CheckResult
{
    /**
     * @var string[]
     */
    public private(set) array $messages = [];

    /**
     * @var string[]
     */
    public private(set) array $errors = [];

    public function addMessage(string $message): void
    {
        $this->messages[] = $message;
    }

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }
}
