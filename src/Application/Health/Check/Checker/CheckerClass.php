<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

/**
 * @internal
 */
final class CheckerClass
{
    /**
     * @return class-string<CheckInterface>
     */
    public static function of(CheckInterface $checker): string
    {
        return $checker instanceof CheckerDecoratorInterface ? $checker->decoratedCheckerClass() : $checker::class;
    }
}
