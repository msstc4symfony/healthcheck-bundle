<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

/**
 * Lets timeout, non-critical and parallel failure messages name a checker by its label instead of its class.
 * AbstractReadinessChecker and the bundle's decorators implement it; a checker decorator of your own should return
 * the checkerLabel() of the checker it wraps, or that checker's class when it has none. An empty label, or one that
 * throws, is reported as the class.
 */
interface LabeledCheckerInterface
{
    /**
     * @return string human-readable identifier, e.g. "DB connection (default)"
     */
    public function checkerLabel(): string;
}
