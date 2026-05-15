<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO;

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

    /**
     * @var string[]
     */
    public private(set) array $warnings = [];

    public function addMessage(string $message): void
    {
        $this->messages[] = $message;
    }

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    public function addWarning(string $warning): void
    {
        $this->warnings[] = $warning;
    }

    /**
     * Truncate trailing messages/errors/warnings back to the recorded sizes. Used by decorators
     * to discard partial inner-state when overriding the outcome (e.g. timeout, criticality).
     */
    public function resetTrailing(int $messagesCount, int $errorsCount, int $warningsCount): void
    {
        $this->messages = array_slice($this->messages, 0, $messagesCount);
        $this->errors = array_slice($this->errors, 0, $errorsCount);
        $this->warnings = array_slice($this->warnings, 0, $warningsCount);
    }
}
