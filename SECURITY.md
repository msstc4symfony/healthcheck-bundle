# Security Policy

## Supported Versions

The bundle follows semantic versioning. Security fixes are released for the latest
minor on each supported major.

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |
| < 1.0   | :x:                |

Runtime requirements are tracked in `composer.json`:

- PHP >= 8.4 (no support for earlier minors — the bundle relies on PHP 8.4
  features: asymmetric visibility, typed class constants, property hooks)
- Symfony 6.4 LTS, 7.x, 8.x

If you are running an older PHP or Symfony version, upgrade before reporting.

## Reporting a Vulnerability

Please **do not** open public GitHub issues, pull requests, or discussions for
security problems.

Use one of the following private channels:

1. **GitHub Security Advisory** (preferred):
   <https://github.com/msstc4symfony/healthcheck-bundle/security/advisories/new>
2. **Email**: `maxim.shamaev@gmail.com`

Include, where possible:

- Affected bundle version and PHP/Symfony versions
- A reproducer (failing test, curl command, or minimal app config)
- Impact and exploitation scenario
- Suggested remediation, if any

You will receive an acknowledgement within **7 days**. Once the report is
triaged, you'll get a timeline for a fix. Coordinated disclosure is appreciated:
please give the maintainer a reasonable window (typically 30–90 days, depending
on severity) before publishing details.

## Scope

This bundle is library code consumed by a host Symfony application. The
following components are in scope for security reports:

- Probe controllers (`/_/healthcheck/{ping,readiness,liveliness}`)
- CLI commands (`healthcheck:liveliness`, `healthcheck:readiness`)
- The `Action` execution pipeline, including the parallel and cached
  decorators
- Compiler passes and detectors under `DependencyInjection/`
- The 17 built-in `CheckInterface` implementations

The following are **out of scope** because they are the host application's
responsibility:

- Network exposure of probe endpoints (see [Operational guidance](#operational-guidance))
- Authentication/authorisation in front of probe endpoints
- Configuration secrets (DSN, credentials) passed to detected services
- Behaviour of third-party infrastructure clients
  (Doctrine DBAL, Redis, RabbitMQ, etc.) that the bundle probes

## Threat Model

The bundle is designed to run on internal/cluster-local network paths
(Kubernetes pod-to-pod, Docker healthcheck, internal LB). Treating probe
endpoints as **public** changes the threat model significantly. Below are the
known attack surfaces and recommended mitigations.

### 1. Information disclosure via probe responses

Readiness and liveliness responses include:

- Service identifiers (e.g. `doctrine.dbal.default_connection`, `mailer.smtp`)
- Adapter class names (`CacheChecker` embeds `$connection::class` in its label)
- Exception messages from underlying clients (DSN fragments, host:port,
  driver errors)

A network attacker that reaches the endpoint can fingerprint your stack and
extract partial connection metadata. **Do not expose `/_/healthcheck/readiness`
or `/_/healthcheck/liveliness` to the public internet.** Restrict access at the
ingress/firewall, or place the probes on a separate internal listener.

### 2. Resource exhaustion / DoS amplification

Every readiness probe triggers real I/O against every detected service:
`SELECT 1`, `PING`, `SET __healthcheck`, an HTTP request, etc.

Each call is wrapped with a timeout decorator
(`HealthCheckerTimeoutDecorationPass`), but the aggregate cost is still
proportional to the number of registered checkers. If probes are publicly
reachable, an attacker can drive load on your databases and message brokers at
no cost to themselves.

Mitigations:

- Keep probes off the public internet.
- Use the cached action decorator (`CachedActionDecorator`) with a small TTL
  (5–10 s) for high-traffic environments.
- Tune the per-checker timeout configuration appropriately. The default is
  intentionally short.

### 3. SSRF via HTTP client checker

`HttpClientChecker` performs an outbound HTTP request to a URL declared in the
bundle configuration (`http_client.<name>.url`). If those URLs are sourced from
**untrusted input** (database, request parameters, user-editable config), an
attacker could induce the application to issue requests to internal endpoints,
cloud metadata services, or arbitrary external hosts.

**Required: treat `http_client` targets as static configuration.** Configure
them in `config/packages/*.yaml` or environment variables that you control —
never derive them from user input or runtime data.

### 4. Cache / lock-store key collision

Cache and lock checkers write a single probe key:
`CheckInterface::PROBE_KEY` = `'__healthcheck'`. If your application uses the
same key for unrelated data, every readiness probe will overwrite it. The
namespace is intentionally unlikely to collide, but if you operate a shared
cache across multiple tenants, ensure your cache prefix isolates them.

The cache write is bounded: TTL is 1 s for Redis/Memcached, the cache adapter's
default for Symfony pools.

### 5. Logging of probe failures

`HealthController` logs failed probe results via PSR-3. If checker error
messages contain sensitive substrings (credentials in driver exceptions, etc.),
those substrings reach your log pipeline. Audit your log sinks before
enabling DEBUG-level transport for the bundle's logger.

### 6. Command-line execution context

`bin/console healthcheck:*` runs the same pipeline as the HTTP path. If
`bin/console` is reachable by less-privileged operating-system users, they can
trigger the same I/O. Treat the console binary as you would any
application-config tool.

## Operational Guidance

Recommended deployment posture:

- **Network**: bind probe endpoints to an internal network or sidecar listener.
  In Kubernetes, the kubelet calls probes over pod-network — no public exposure
  needed.
- **Authentication**: if probes must be reachable beyond the cluster, place a
  reverse proxy in front and require mTLS or a shared secret header.
- **Rate limiting**: a single Kubernetes probe per 5–10 s is the normal rate.
  Anything significantly higher is suspicious; rate-limit at the ingress.
- **Observability**: forward `HealthCheckRunCompleted` event payloads to your
  metrics backend (latency, error count per checker) so you can detect
  degradation without polling the endpoint externally.

## Dependency & Static-Analysis Hygiene

The project's quality gate (`make check`) enforces several security-relevant
controls:

- `composer audit` runs on every CI build; advisories from
  `roave/security-advisories` block dependency installation.
- PHPStan level 9 with `spaze/phpstan-disallowed-calls`, `phpstan-strict-rules`,
  `phpstan-symfony`, and `phpstan-doctrine` rules.
- Rector applies the PHP 8.4 quality and dead-code rulesets.
- PHPUnit runs in strict mode (`failOnRisky`, `failOnWarning`,
  `failOnPhpunitDeprecation`).

When contributing, do not bypass `make check` (e.g., via `--no-verify`). New
findings are not auto-baselined.

## Credits

Thank you to anyone who responsibly discloses a vulnerability. Reporters are
acknowledged in release notes unless they request otherwise.
