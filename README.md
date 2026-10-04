# Healthcheck Symfony Bundle

![Build Status](https://github.com/msstc4symfony/healthcheck-bundle/actions/workflows/checks.yml/badge.svg?branch=main)
[![codecov](https://codecov.io/github/msstc4symfony/healthcheck-bundle/branch/main/graph/badge.svg?token=NO9FYTJMSU)](https://codecov.io/github/msstc4symfony/healthcheck-bundle/branch/main)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.4-787CB5?logo=php&logoColor=white)](https://php.net)
[![Symfony Versions](https://img.shields.io/badge/Symfony-7.4%20%7C%208.x-000000?logo=symfony&logoColor=white)](https://symfony.com)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%2010-2a5ea7)](https://phpstan.org)
[![Last commit](https://img.shields.io/github/last-commit/msstc4symfony/healthcheck-bundle/main)](https://github.com/msstc4symfony/healthcheck-bundle/commits/main)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

A Symfony bundle for comprehensive health checking of your application and its external dependencies. Provides both **liveliness** and **readiness** probes compatible with Kubernetes health checks.

## What's the difference between Liveness and Readiness?

- **Liveness** (`/healthcheck/liveliness`): Checks if the application itself is running properly. If this fails, the application should be restarted. Only verifies the application's internal state without checking external dependencies.

- **Readiness** (`/healthcheck/readiness`): Checks if the application is ready to serve traffic by verifying all external connections (databases, cache, message queues, etc.). If this fails, the application should not receive traffic until all dependencies are available.

## Requirements

- PHP >= 8.4
- Symfony 7.4 | 8.x

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
- **Symfony Cache Pools** — `cache.pool`-tagged services backed by infrastructure: Redis/Valkey, Memcached, PDO, Doctrine DBAL, Couchbase, third-party adapters, and chains containing one of them. Pools that live in memory or on the local disk (Array, APCu, Filesystem, PhpFiles, PhpArray, Null) — including FrameworkBundle's system pools `cache.system`, `cache.validator`, `cache.serializer`, … and a filesystem `cache.app` — are not probed
- **Redis** / **Predis** / **Memcached** / **Memcache** — cache clients detected by class
- **RabbitMQ** — `old_sound_rabbit_mq.connection`-tagged services
- **Elastica** — Elasticsearch clients
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

`Warnings` lists failures of checkers configured as `non_critical` — including an exception thrown by such a checker, reported as `<checker class> failed (<message>)`: they are reported but do not fail the probe. Every line of a section is tab-indented; an empty section reads `Errors: none`. Responses carry `Vary: Accept`.

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

class CustomServiceChecker implements CheckInterface
{
    public function __construct(
        private readonly YourService $service,
    ) {
    }

    public function isSupport(Context $context): bool
    {
        // This checker only runs in readiness mode
        // For liveliness checks, use: CheckTypeEnum::LIVELINESS
        return CheckTypeEnum::READINESS === $context->type;
    }

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

Readiness-only checkers can extend `AbstractReadinessChecker`, which formats the output and masks credentials in failure messages. `doCheck()` throws to fail; a returned string is appended to the success line:

```php
final readonly class SearchChecker extends AbstractReadinessChecker
{
    public function __construct(private SearchClient $client)
    {
    }

    protected function doCheck(): ?string
    {
        return 'cluster status: ' . $this->client->health(); // "Search passed (cluster status: green)"
    }

    protected function label(): string
    {
        return 'Search';
    }
}
```

Override `skipReason()` to report `<label> skipped (<reason>)` instead of probing.

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
        class: Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CacheChecker
        arguments: ['@cache.app', 'cache.app', ~] # pool, pool id, parent pool id (none)
        tags:
            - 'Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface'
```

If the pool later moves to a remote adapter, the bundle probes it on its own — drop the manual service then, or the pool is probed twice.

### Checker for Both Liveness and Readiness

If your checker should run in both modes, return `true` for both types:

```php
public function isSupport(Context $context): bool
{
    // Runs in both liveliness and readiness checks
    return in_array($context->type, [CheckTypeEnum::READINESS, CheckTypeEnum::LIVELINESS], true);
}
```

### Checker Only for Liveness

```php
public function isSupport(Context $context): bool
{
    // Only runs in liveliness checks
    return CheckTypeEnum::LIVELINESS === $context->type;
}
```

### Decorating a Checker

Run events and failure messages name the checker class. A decorator of your own should implement `CheckerDecoratorInterface` so they name the checker it wraps (return the wrapped decorator's `decoratedCheckerClass()` when it wraps another decorator).

### Non-critical Checkers and Timeouts

```yaml
msstc4symfony_healthcheck:
    non_critical: ['healthcheck.checker.lock.default']
    timeouts:
        default_ms: 2000
        overrides:
            healthcheck.checker.doctrine.dbal.default_connection: 5000
```

Both options take checker service ids. An id no checker has fails the container build with the list of known ids. Quote ids containing brackets in YAML, e.g. `'healthcheck.checker.lock.invoice[1]'` (a combined lock store).

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
