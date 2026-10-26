# Healthcheck Symfony Bundle

![Build Status](https://github.com/msstc4symfony/healthcheck-bundle/actions/workflows/checks.yml/badge.svg?branch=main)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-787CB5?logo=php&logoColor=white)](https://php.net)
[![Symfony Versions](https://img.shields.io/badge/Symfony-7.4%20%7C%208.x-000000?logo=symfony&logoColor=white)](https://symfony.com)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%2010-2a5ea7)](https://phpstan.org)
[![Last commit](https://img.shields.io/github/last-commit/msstc4symfony/healthcheck-bundle/main)](https://github.com/msstc4symfony/healthcheck-bundle/commits/main)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

A Symfony bundle for comprehensive health checking of your application and its external dependencies. Provides both **liveliness** and **readiness** probes compatible with Kubernetes health checks.

## What's the difference between Liveness and Readiness?

- **Liveness** (`/healthcheck/liveliness`): Checks if the application itself is running properly. If this fails, the application should be restarted. Only verifies the application's internal state without checking external dependencies.

- **Readiness** (`/healthcheck/readiness`): Checks if the application is ready to serve traffic by verifying all external connections (databases, cache, message queues, etc.). If this fails, the application should not receive traffic until all dependencies are available.

## Requirements

- PHP >= 8.2 (bundle 1.0–1.2 needs PHP 8.4)
- Symfony 7.4 | 8.x (Symfony 8 needs PHP 8.4)

## Installation

The package is not on Packagist yet, so register its GitHub repository first:

```bash
composer config repositories.msstc4symfony-healthcheck vcs https://github.com/msstc4symfony/healthcheck-bundle
composer require msstc4symfony/healthcheck-bundle
```

If you're not using Symfony Flex, you'll need to manually enable the bundle in your `config/bundles.php`:

```php
return [
    // ...
    Msstc4Symfony\HealthCheckBundle\HealthCheckBundle::class => ['all' => true],
];
```

## Built-in Checkers

The bundle automatically detects and registers health checkers for the following services when their corresponding clients are present in the container:

- **Doctrine DBAL** (3.x and 4.x) — database connections (`doctrine.dbal.*_connection`)
- **Doctrine ORM** — entity managers (`doctrine.orm.*_entity_manager`)
- **Doctrine MongoDB ODM** — MongoDB connections + document managers
- **Doctrine Migrations** — schema-status probe
- **Symfony Cache Pools** — `cache.pool`-tagged services backed by infrastructure, one checker per pool (`healthcheck.checker.cache.pool.<name>`, label `Cache (<name>) connection`):
  - Probed: Redis/Valkey, Memcached, PDO, Doctrine DBAL, Couchbase, third-party adapters, and chains containing one of them.
  - Not probed: pools in memory or on the local disk (Array, APCu, Filesystem, PhpFiles, PhpArray, Null), including FrameworkBundle's system pools `cache.system`, `cache.validator`, `cache.serializer`, … and a filesystem `cache.app`.
  - Shared connection: pools of a `RedisAdapter`, `MemcachedAdapter` or `CouchbaseCollectionAdapter` (subclasses included) on the same connection service — pools on one Redis DSN or `provider` share it — probe it **once per run**. The first such pool really probes; the others report its outcome without touching the connection: `<label> passed (shared connection probed via <first label>)`, or `<label>: shared connection probe via <first label> failed: <message>`. With the server down this costs one connect timeout instead of one per pool. The outcome is kept for one probe run only. A reused pool reports about 0 ms in its `HealthCheckerCompletedEvent` (so per-pool latency metrics show the one real probe on the first pool and ~0 on the others), but its own `timeouts` budget is compared with the duration of the real probe: a pool with a smaller budget than the slow first one fails `exceeded budget` too. A connection that fails while the pool service is built (an eager client) is shared the same way: the target is built once. Every pool still has its checker, id, event and `non_critical` / `timeouts.overrides` key.
  - Not shared: a `RedisTagAwareAdapter` pool shares only with other tag-aware pools on that connection (it also fails on the eviction policy); `PdoAdapter` and `DoctrineDbalAdapter` pools are each probed (they fail per table); chains, third-party adapters, and pools wired with a named (`$redis: …`) instead of a positional first argument are each probed too. A `tags: true` pool is tagged as its inner plain adapter and shares like one.
  - The shared probe proves the connection and the first pool, not the namespace or marshaller of the others; a failure of the first pool's own namespace (a Redis ACL key pattern, its marshaller) is reported for the others too. On a cluster or multi-server connection (Redis Cluster, `RedisArray`, several Memcached servers) the one probe reaches one shard or server, so a single node down may go unseen by the pools.
- **Redis** / **Predis** — `\Redis`, `\RedisCluster` and `Predis\Client` clients, subclasses included (see [Redis](#redis))
- **Memcached** / **Memcache** — cache clients detected by class, subclasses included
- **RabbitMQ** — `old_sound_rabbit_mq.connection`-tagged services
- **Elastica** (7, 8 and 9) — Elasticsearch clients
- **OpenSearch** — OpenSearch clients
- **ClickHouse** — ClickHouseDB clients
- **Kafka** — `RdKafka\Producer` / `RdKafka\KafkaConsumer`
- **Symfony Messenger** — transports implementing `MessageCountAwareInterface`
- **Symfony Mailer** — SMTP transports (`SmtpTransport` subclasses)
- **Symfony Lock** — the stores behind configured `framework.lock` resources (tagged `lock.store`) and `PersistingStoreInterface` services registered by the application. Stores with hidden (dot-prefixed) ids that no `framework.lock` resource uses — e.g. FrameworkBundle 8.1's predefined `.lock.flock.store` / `.lock.semaphore.store` — are not probed. Stores built by FrameworkBundle are named after the resources that use them instead of their hashed service id: checker id `healthcheck.checker.lock.default` / label `Lock store (lock.default)`; `lock.invoice[1]` for the second store of a combined resource; a store shared by resources joins their sorted names — `healthcheck.checker.lock.default+lock.reports`, `Lock store (lock.default, lock.reports)`. Use these ids as `non_critical` / `timeouts.overrides` keys; a store no resource uses keeps its service id. When `framework.lock` names a connection service (e.g. a `\Redis` client or a DBAL connection) that the bundle already probes, the lock store built around it is not probed a second time — unless only `non_critical` checkers probe that connection, or the store's own checker id is listed in `non_critical` / `timeouts.overrides`
- **Flysystem** — `flysystem.storage`-tagged services
- **HTTP probes** — arbitrary configured URLs via `http_client` bundle config

Readiness-only checkers are built inside the readiness probe: a client whose constructor throws (missing extension, invalid DSN) fails only its own check, e.g. `Lock store (…) failed (…)`, and never affects liveliness. Credentials in URLs (`scheme://user:pass@host`) and secret query parameters are masked in failure messages.

All detections happen automatically when the relevant package is installed and a service is registered. Third-party packages can contribute their own detectors by implementing `CheckerDetectorInterface` and registering the service — the bundle picks them up via the `healthcheck.detector` tag. A detector whose checker probes a client built around another service (as a lock store wraps a `\Redis` connection) can also implement `WrappedTargetDetectorInterface`: `wrappedTarget()` returns the id of that wrapped service (or `null`), and the checker is skipped when a checker outside `non_critical` already probes it — unless its own id is listed in `non_critical` / `timeouts.overrides`.

### Redis

Detected clients: `\Redis` and its subclasses (e.g. SncRedisBundle's phpredis client), `\RedisCluster` and its subclasses, and `Predis\Client` and its subclasses (single server, cluster, replication and sentinel). Each client service gets its own checker `healthcheck.checker.<service id>`, labelled `Redis connection` (`Redis cluster connection` for `\RedisCluster`).

The probe writes the key `__healthcheck` with `SET __healthcheck <unix time> EX 1`, so the connection needs write permission for that key (an ACL that allows `SET` on it). A read-only replica fails the probe; a Predis replication or sentinel client sends the write to the master, a cluster client to the node owning the key.

The bundle does not configure connections. TLS (`rediss://` / `tls://` DSNs, stream context, CA and client certificates) is set up in the client itself; a TLS handshake failure is reported like an unreachable Redis — `Redis connection failed (<client message>)`. Relay (`Relay\Relay`) clients are not supported.

## Usage

### HTTP Endpoints

#### Readiness Check

```bash
GET /_/healthcheck/readiness
```

Checks all external connections and dependencies.

**Response Codes:**
- `200` - Everything is working correctly, application is ready to serve traffic
- `406` - Something is wrong with one or more dependencies

**Text Response Example (default):**
```
Result: up
Errors: none
Messages:
	Redis connection passed
	Database connection passed
Warnings:
	Cache (cache.app) connection failed (Connection refused)
```

`Warnings` lists failures of checkers configured as `non_critical` — including an exception thrown by such a checker, reported as `<label> failed (<message>)` (the checker class for a checker without `LabeledCheckerInterface`): they are reported but do not fail the probe. Every line of a section is tab-indented; an empty section reads `Errors: none`. Responses carry `Vary: Accept`.

**JSON Response Example** — request `?_format=json` or send an `Accept: application/json` header (the query parameter wins over the header):
```bash
curl 'http://localhost/_/healthcheck/readiness?_format=json'
curl -H 'Accept: application/json' http://localhost/_/healthcheck/readiness
```
```json
{
    "success": true,
    "errors": [],
    "messages": [
        "Redis connection passed",
        "Database connection passed"
    ],
    "warnings": [
        "Cache (cache.app) connection failed (Connection refused)"
    ]
}
```

**Error Response Example:**
```json
{
    "success": false,
    "errors": [
        "Redis connection failed",
        "Database connection timeout"
    ],
    "messages": [],
    "warnings": []
}
```

#### Liveness Check

```bash
GET /_/healthcheck/liveliness
```

Checks only the application itself without testing external dependencies.

**Response Codes:**
- `200` - Application is running correctly
- `406` - Application has internal problems

**Response Format:** Same as readiness check (text by default, JSON via `?_format=json` or `Accept: application/json`)

#### Ping Endpoint

```bash
GET /_/healthcheck/ping
```

Simple ping endpoint that always returns `pong` with HTTP 200. Useful for basic connectivity checks.

### CLI Commands

#### Readiness Check

```bash
./bin/console healthcheck:readiness
```

Check all connections via command line.

**Exit Codes:**
- `0` - Everything is working correctly
- `1` - Something is wrong

**Example Output** (messages are shown with `-v` or when the check fails; errors and warnings always):
```
Result: success
Messages:
    Redis connection passed
    Database connection passed
Warnings:
    Cache (cache.app) connection failed (Connection refused)
```

#### Liveness Check

```bash
./bin/console healthcheck:liveliness
# or shorter alias:
./bin/console healthcheck
```

Checks only the application itself via command line.

**Exit Codes:**
- `0` - Application is running correctly
- `1` - Application has problems

## Customization

### Creating a Custom Checker

To create a custom health checker, implement the `CheckInterface`:

```php
<?php

namespace App\HealthCheck;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Override;

class CustomServiceChecker implements CheckInterface
{
    public function __construct(
        private readonly YourService $service,
    ) {
    }

    #[Override]
    public function isSupport(Context $context): bool
    {
        // This checker only runs in readiness mode
        // For liveliness checks, use: CheckTypeEnum::LIVELINESS
        return CheckTypeEnum::READINESS === $context->type;
    }

    #[Override]
    public function check(CheckResult $result, Context $context): CheckResult
    {
        try {
            $this->service->ping();
            $result->addMessage('Custom service connection passed');
        } catch (\Exception $e) {
            $result->addError('Custom service connection failed: ' . $e->getMessage());
        }

        return $result;
    }
}
```

### Readiness Checker Template

Readiness-only checkers can extend `AbstractReadinessChecker`, which formats the output and masks credentials in failure messages. `doCheck()` throws to fail; a returned string is appended to the success line. `AbstractReadinessChecker` is a readonly class, so a subclass must be a named `readonly class` (PHP 8.2 has no anonymous readonly classes):

```php
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\AbstractReadinessChecker;
use Override;

final readonly class SearchChecker extends AbstractReadinessChecker
{
    public function __construct(private SearchClient $client)
    {
    }

    #[Override]
    protected function doCheck(): ?string
    {
        return 'cluster status: ' . $this->client->health(); // "Search passed (cluster status: green)"
    }

    #[Override]
    protected function label(): string
    {
        return 'Search';
    }
}
```

Override `skipReason()` to report `<label> skipped (<reason>)` instead of probing.

Override `sharedProbeKey()` (a non-empty string, default null) when several checkers probe one backend: within one probe run only the first checker with a given key runs `doCheck()`, the others report its outcome (see the shared cache pool connection above). Only the outcome of `doCheck()` is shared: a checker of yours whose service fails while it is built is not, as the bundle learns the key of its own cache pool checkers only.

### Registering the Checker

The bundle uses Symfony's autoconfiguration with the `#[AutoconfigureTag]` attribute. If your checker is in a directory with autoconfiguration enabled, it will be automatically registered.

Alternatively, you can manually tag your service in `services.yaml` (a child of an abstract `parent` template works too):

```yaml
services:
    App\HealthCheck\CustomServiceChecker:
        tags:
            - 'Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface'
```

### Probing a Local Cache Pool

Pools on local adapters (e.g. `cache.app` on the filesystem) are not probed automatically. If such a pool is a real dependency — a filesystem cache on a shared volume — register a `CacheChecker` for it:

```yaml
services:
    app.healthcheck.cache_app:
        class: Msstc4Symfony\HealthCheckBundle\Infrastructure\Checker\CacheChecker
        arguments: ['@cache.app', 'cache.app', ~] # pool, pool id, parent pool id (none)
        tags:
            - 'Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface'
```

If the pool later moves to a remote adapter, the bundle probes it on its own — drop the manual service then, or the pool is probed twice.

### Checker for Both Liveness and Readiness

If your checker should run in both modes, return `true` for both types:

```php
#[Override]
public function isSupport(Context $context): bool
{
    // Runs in both liveliness and readiness checks
    return in_array($context->type, [CheckTypeEnum::READINESS, CheckTypeEnum::LIVELINESS], true);
}
```

### Checker Only for Liveness

```php
#[Override]
public function isSupport(Context $context): bool
{
    // Only runs in liveliness checks
    return CheckTypeEnum::LIVELINESS === $context->type;
}
```

### Decorating a Checker

Run events name the checker class. A decorator of your own should implement `CheckerDecoratorInterface` so they name the checker it wraps (return the wrapped decorator's `decoratedCheckerClass()` when it wraps another decorator).

The timeout, non-critical and parallel failure messages name the checker by `LabeledCheckerInterface::checkerLabel()`, falling back to its class: `AbstractReadinessChecker` returns its `label()`, the bundle's decorators the label of the checker they wrap. A checker that implements `CheckInterface` directly, or a decorator of your own, can implement it too — a decorator returns the `checkerLabel()` of the checker it wraps, or its class when that one has no label.

### Non-critical Checkers and Timeouts

```yaml
msstc4symfony_healthcheck:
    non_critical: ['healthcheck.checker.lock.default']
    timeouts:
        default_ms: 2000
        overrides:
            healthcheck.checker.doctrine.dbal.default_connection: 5000
```

`non_critical` and `timeouts.overrides` take checker service ids. An id no checker has fails the container build with the list of known ids. Quote ids containing brackets in YAML, e.g. `'healthcheck.checker.lock.invoice[1]'` (a combined lock store).

#### Probe budget

```yaml
msstc4symfony_healthcheck:
    timeouts:
        probe_ms: 5000
```

`timeouts.probe_ms` (positive integer; unset or `~` = unlimited, the default; 0, negatives and non-integers are rejected) bounds the whole probe run. Before a checker starts, the time since the run began is compared with the budget; once it is exceeded the checker is not run and reports `<label> skipped: probe budget exhausted (<elapsed> ms > <budget> ms)` — an error, or a warning for a `non_critical` checker. The first checker of a run always runs, whatever the budget. The budget gates checkers that have **not started**; a checker already running is never interrupted (see below), so a run can still end past `probe_ms` by the length of its last checker. With `execution: parallel` the checkers still start one after another (synchronous Fibers), so it behaves the same. It is enforced by the bundle's own decorators, which wrap every checker.

A skipped checker counts as **failed** (or **degraded**, when `non_critical`) in the run result and in `HealthCheckerCompletedEvent` (`$success` false, or true with `$warned`), with a duration of about 0 ms, so metrics built on the events count it as failed or degraded. A separate "skipped" status does not exist.

#### Timeouts are checked after the probe returns

`timeouts.default_ms` and `timeouts.overrides` are compared with the elapsed time once the probe has returned: synchronous PHP cannot interrupt a blocking driver call, so a hung connection is not cut short by the bundle and a slow probe is only reported as failed afterwards. Bound the time at the client level instead; the option names differ per client:

- Elastica 7: top-level client options `timeout` and `connectTimeout`.
- Elastica 8 and 9: no top-level timeout keys; set the underlying HTTP client's own option names in `transport_config.http_client_options` (Symfony HttpClient: `timeout`, `max_duration`; Guzzle: `timeout`, `connect_timeout`). Elastica rejects options for HTTP clients it has no adapter for.
- Redis: phpredis takes a connect timeout and a `read_timeout`; with Symfony's Redis DSN use `?timeout=2&read_timeout=2`; Predis takes `timeout` (connect) and `read_write_timeout`.
- PDO / Doctrine DBAL: pgsql `connect_timeout=N` in the DSN, plus `options='-c statement_timeout=N'` (milliseconds) to bound queries; mysql `PDO::ATTR_TIMEOUT` bounds the connect only; sqlite `PDO::ATTR_TIMEOUT` is the busy timeout.
- Symfony HttpClient (HTTP probes): `timeout` is an idle timeout (no data received), `max_duration` caps the total request time.

Even with these set, the bundle only reports an over-limit probe after the fact; the client options are what keep it from blocking.

## Events

Each probe run dispatches, through the application's event dispatcher (a listener that throws cannot fail the
probe):

- `HealthCheckRunStartedEvent` — `$request`;
- `HealthCheckerCompletedEvent`, once per checker — `$checkerClass` (behind the bundle's decorators), `$durationMs`,
  `$success` (no error added), `$warned` (a warning added: a non-critical checker that failed), `$checkerId` (its
  service id) and `$type` (the probe);
- `HealthCheckRunCompletedEvent` — `$response`, `$totalDurationMs` and `$type`.

`$checkerId` and `$type` are nullable: null only in an event built by hand or from an Action given its checkers as a
list.

A run the result cache answers dispatches none of them.

A checker that reuses a shared probe (cache pools on one connection) or is skipped by `timeouts.probe_ms` completes in about 0 ms; the event carries the shared outcome (`$success` false for a failed shared probe).

## Public API

Applications use, implement or configure only these; every other class is `@internal` and may change in a minor
release.

| Namespace `Msstc4Symfony\HealthCheckBundle\` | Classes |
|---|---|
| `Application\Health\Check\Checker` | `CheckInterface`, `AbstractReadinessChecker`, `CheckerDecoratorInterface`, `LabeledCheckerInterface` |
| `Application\Health\Check\DTO` | `CheckResult`, `Context`, `Request`, `Response` |
| `Application\Health\Check\Enum` | `CheckTypeEnum` |
| `Application\Health\Check\Event` | `HealthCheckRunStartedEvent`, `HealthCheckerCompletedEvent`, `HealthCheckRunCompletedEvent` |
| `Application\Health\Check` | `ActionInterface` |
| `Infrastructure\Checker` | every built-in checker (`DBALConnectionChecker`, `RedisChecker`, …): registered and decorated by class; `HttpProbeTarget` (targets of a manually registered `HttpClientChecker`) |
| `DependencyInjection\Detector` | `CheckerDetectorInterface`, `WrappedTargetDetectorInterface` — third-party detectors (tag `healthcheck.detector`, autoconfigured) |
| (root) | `HealthCheckBundle` |

The configuration keys under `msstc4symfony_healthcheck`, the routes and the console commands are public too.

Upgrading from 1.x: 2.0 moved the built-in checkers from `Application\Health\Check\Checker\` to
`Infrastructure\Checker\` (with `HttpProbeTarget`) and the compiler passes to `DependencyInjection\CompilerPass\` — see the
CHANGELOG.

## Docker Compose Integration Example
You can configure health checks for your services in `docker-compose.yaml`:

```yaml
services:
  php-fpm:
    healthcheck:
      test: ./bin/console healthcheck:liveliness
      interval: 10s
      timeout: 3s
      start_period: 10s

  nginx:
    healthcheck:
      test: [ "CMD", "curl", "--fail", "http://localhost/_/healthcheck/liveliness" ]
      interval: 10s
      timeout: 3s
      start_period: 10s
```

**Parameters Explanation:**
- `test` - The command or HTTP request to check the service health
- `interval` - Time between health checks
- `timeout` - Maximum time to wait for a health check to complete
- `start_period` - Grace period during container startup before failed health checks count towards the maximum number of retries

## Kubernetes Integration Example

```yaml
apiVersion: v1
kind: Pod
metadata:
  name: my-app
spec:
  containers:
  - name: app
    image: my-app:latest
    livelinessProbe:
      httpGet:
        path: /_/healthcheck/liveliness
        port: 8080
      initialDelaySeconds: 3
      periodSeconds: 10
    readinessProbe:
      httpGet:
        path: /_/healthcheck/readiness
        port: 8080
      initialDelaySeconds: 5
      periodSeconds: 5
```

## Local Development

Check code quality:
```bash
make check
```

Fix code style issues:
```bash
make fix
```

## License

This bundle is released under the [MIT License](https://opensource.org/licenses/MIT).

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.
