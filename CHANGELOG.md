# Changelog

All notable changes to this bundle are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versions follow
[Semantic Versioning](https://semver.org/); dates are UTC.

## [1.0.0] - 2026-10-04

First release of `msstc4symfony/healthcheck-bundle` (namespace `Msstc4Symfony\HealthCheckBundle`).

### Added

- Kubernetes-style probes over HTTP — `GET /_/healthcheck/readiness`, `/_/healthcheck/liveliness`,
  `/_/healthcheck/ping` (text by default, JSON via `?_format=json` or `Accept: application/json`) —
  and the `healthcheck:readiness` / `healthcheck:liveliness` console commands.
- Auto-detected checkers for Doctrine DBAL 3 / 4, ORM, MongoDB ODM and Migrations, infrastructure
  cache pools (local pools are skipped), Redis / Predis / Memcached / Memcache, RabbitMQ, Elastica,
  OpenSearch, ClickHouse, Kafka, Messenger transports, Mailer SMTP transports, `framework.lock`
  stores (named after their resources), Flysystem and configured HTTP probes.
- Readiness-only checkers are built inside the readiness probe, so an unconstructible client never
  breaks liveliness; credentials are masked in failure messages.
- Extension points: `CheckInterface`, `AbstractReadinessChecker`, `CheckerDetectorInterface`
  (`healthcheck.detector` tag), `WrappedTargetDetectorInterface`, `CheckerDecoratorInterface`,
  checker run events.
- Configuration under the `msstc4symfony_healthcheck` root: `execution` (sequential / parallel),
  `cache` (`enabled`, `ttl_seconds`, `pool`), `non_critical`, `timeouts` (`default_ms`,
  `overrides`), `http_client` probes; unknown checker ids fail the container build.

### Requirements

- PHP >= 8.4, Symfony ^7.4|^8.0, `psr/log` ^3.0.
- The client library of each checked service (Doctrine, Redis, Kafka, ...) is optional.

[1.0.0]: https://github.com/msstc4symfony/healthcheck-bundle/releases/tag/v1.0.0
