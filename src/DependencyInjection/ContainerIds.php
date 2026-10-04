<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\DependencyInjection;

/**
 * Container parameter and service ids the bundle defines; the single source of truth for them.
 */
final readonly class ContainerIds
{
    public const string ALIAS = 'msstc4symfony_healthcheck';

    public const string PARAM_HTTP_CLIENT_TARGETS = 'msstc4symfony_healthcheck.http_client_targets';

    public const string PARAM_DEFAULT_TIMEOUT_MS = 'msstc4symfony_healthcheck.default_timeout_ms';

    public const string PARAM_TIMEOUT_OVERRIDES = 'msstc4symfony_healthcheck.timeout_overrides';

    public const string PARAM_NON_CRITICAL_CHECKERS = 'msstc4symfony_healthcheck.non_critical_checkers';

    public const string SERVICE_CACHED_ACTION = 'msstc4symfony_healthcheck.action.cached';

    public const string SERVICE_PARALLEL_ACTION = 'msstc4symfony_healthcheck.action.parallel';
}
