<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

/**
 * Lets run events and failure messages name the checker a bundle decorator wraps instead of the
 * decorator itself.
 *
 * @internal implemented by the bundle's own checker decorators only
 */
interface CheckerDecoratorInterface
{
    /**
     * @return class-string<CheckInterface>
     */
    public function decoratedCheckerClass(): string;
}
