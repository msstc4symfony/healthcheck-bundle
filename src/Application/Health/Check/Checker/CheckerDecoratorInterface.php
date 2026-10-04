<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

/**
 * Lets run events and failure messages name the checker a decorator wraps instead of the decorator
 * itself. Implement it on your own checker decorators so reports name the real checker class.
 */
interface CheckerDecoratorInterface
{
    /**
     * @return class-string<CheckInterface> the innermost checker's class; ask an inner decorator
     *                                      for its own decoratedCheckerClass()
     */
    public function decoratedCheckerClass(): string;
}
