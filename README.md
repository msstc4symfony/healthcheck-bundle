# Healthcheck Symfony Bundle

![Build Status](https://github.com/msstc4symfony/healthcheck-bundle/actions/workflows/checks.yml/badge.svg?branch=main)
[![codecov](https://codecov.io/github/msstc4symfony/healthcheck-bundle/branch/main/graph/badge.svg?token=NO9FYTJMSU)](https://codecov.io/github/msstc4symfony/healthcheck-bundle/branch/main)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.4-787CB5?logo=php&logoColor=white)](https://php.net)
[![Symfony Versions](https://img.shields.io/badge/Symfony-6.4%20%7C%207.x%20%7C%208.x-000000?logo=symfony&logoColor=white)](https://symfony.com)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%209-2a5ea7)](https://phpstan.org)
[![Last commit](https://img.shields.io/github/last-commit/msstc4symfony/healthcheck-bundle/main)](https://github.com/msstc4symfony/healthcheck-bundle/commits/main)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

A Symfony bundle for comprehensive health checking of your application and its external dependencies. Provides both **liveliness** and **readiness** probes compatible with Kubernetes health checks.

## What's the difference between Liveness and Readiness?

- **Liveness** (`/healthcheck/liveliness`): Checks if the application itself is running properly. If this fails, the application should be restarted. Only verifies the application's internal state without checking external dependencies.

- **Readiness** (`/healthcheck/readiness`): Checks if the application is ready to serve traffic by verifying all external connections (databases, cache, message queues, etc.). If this fails, the application should not receive traffic until all dependencies are available.

## Requirements

- PHP >= 8.4
- Symfony 6.4 | 7.x | 8.x

## Installation

The package lives in a private GitHub repository, so register it as a VCS
repository first. `no-api` makes Composer clone over SSH instead of calling the
GitHub API, which would need a token for a private repository:

```bash
composer config repositories.msstc4symfony-healthcheck '{"type": "vcs", "url": "git@github.com:msstc4symfony/healthcheck-bundle.git", "no-api": true}'
composer require msstc4symfony/healthcheck-bundle
```

Without a GitHub token Composer cannot download dist archives of a private
repository; either add one (`composer config github-oauth.github.com <token>`)
or install from source (`composer require --prefer-source ...`).

If you're not using Symfony Flex, you'll need to manually enable the bundle in your `config/bundles.php`:

```php
return [
    // ...
    Msstc4Symfony\HealthCheckBundle\HealthCheckBundle::class => ['all' => true],
];
```

## Built-in Checkers

The bundle automatically detects and registers health checkers for the following services when their corresponding clients are present in the container:

- **Doctrine DBAL** — database connections (`doctrine.dbal.*_connection`)
- **Doctrine ORM** — entity managers (`doctrine.orm.*_entity_manager`)
- **Doctrine MongoDB ODM** — MongoDB connections + document managers
- **Doctrine Migrations** — schema-status probe
- **Symfony Cache Pools** — all `cache.pool`-tagged services
- **Redis** / **Predis** / **Memcached** / **Memcache** — cache clients detected by class
- **RabbitMQ** — `old_sound_rabbit_mq.connection`-tagged services
- **Elastica** — Elasticsearch clients
- **OpenSearch** — OpenSearch clients
- **ClickHouse** — ClickHouseDB clients
- **Kafka** — `RdKafka\Producer` / `RdKafka\KafkaConsumer`
- **Symfony Messenger** — transports implementing `MessageCountAwareInterface`
- **Symfony Mailer** — SMTP transports (`SmtpTransport` subclasses)
- **Symfony Lock** — `PersistingStoreInterface` implementations
- **Flysystem** — `flysystem.storage`-tagged services
- **HTTP probes** — arbitrary configured URLs via `http_client` bundle config

All detections happen automatically when the relevant package is installed and a service is registered. Third-party packages can contribute their own detectors by implementing `CheckerDetectorInterface` and registering the service — the bundle picks them up via the `healthcheck.detector` tag.

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
    Cache pool 'app' connection passed
```

**JSON Response Example** (send `Content-Type: application/json` header):
```json
{
    "success": true,
    "errors": [],
    "messages": [
        "Redis connection passed",
        "Database connection passed",
        "Cache pool 'app' connection passed"
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
    "messages": []
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

**Response Format:** Same as readiness check (text or JSON based on `Content-Type` header)

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

**Example Output:**
```
Result: up
Errors: none
Messages:
    Redis connection passed
    Database connection passed
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
            $result->messages[] = 'Custom service connection passed';
        } catch (\Exception $e) {
            $result->errors[] = 'Custom service connection failed: ' . $e->getMessage();
        }

        return $result;
    }
}
```

### Registering the Checker

The bundle uses Symfony's autoconfiguration with the `#[AutoconfigureTag]` attribute. If your checker is in a directory with autoconfiguration enabled, it will be automatically registered.

Alternatively, you can manually tag your service in `services.yaml`:

```yaml
services:
    App\HealthCheck\CustomServiceChecker:
        tags:
            - { name: 'healthcheck.checker' }
```

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
