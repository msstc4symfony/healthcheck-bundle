<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Application\Health\Check;

/**
 * Masks credentials in failure messages before they reach probe output: client constructors and
 * factories (e.g. Lock's StoreFactory) quote the full DSN, password included.
 *
 * @internal
 */
final class CredentialRedactor
{
    private const string URL_USER_INFO = '~\b([a-z][a-z0-9+.\-]*://)[^\s/?#@"\']+@~i';

    private const string SECRET_QUERY_PARAMETER = '~([?&](?:password|passwd|pass|pwd|secret|token|api_key|apikey|auth)=)[^&\s"\'#]+~i';

    public static function redact(string $message): string
    {
        return (string) preg_replace(
            [self::URL_USER_INFO, self::SECRET_QUERY_PARAMETER],
            ['$1***@', '$1***'],
            $message,
        );
    }
}
