<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

use Throwable;

/**
 * @internal
 */
final class CheckerLabel
{
    public static function of(CheckInterface $checker): string
    {
        if ($checker instanceof LabeledCheckerInterface) {
            try {
                $label = $checker->checkerLabel();
            } catch (Throwable) {
                // Called while reporting a failure: a broken label must not turn one failing checker into a failed probe.
                $label = '';
            }

            if ($label !== '') {
                return $label;
            }
        }

        return CheckerClass::of($checker);
    }
}
