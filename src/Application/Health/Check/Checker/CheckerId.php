<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker;

/**
 * @internal
 */
final class CheckerId
{
    /**
     * The service id of a checker from its key in the Action's iterable; a list key, or an all-digit id PHP casts to
     * int, names no service.
     *
     * @return non-empty-string|null
     */
    public static function of(int|string $key): ?string
    {
        return is_string($key) && $key !== '' ? $key : null;
    }
}
