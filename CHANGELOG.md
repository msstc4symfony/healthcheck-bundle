# Changelog

All notable changes to this bundle are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versions follow
[Semantic Versioning](https://semver.org/); dates are UTC.

## [1.2.0] - 2026-10-07

### Changed

- The Predis readiness check does a real round trip, `SET __healthcheck <time> EX 1`, like the phpredis check; it used to pass on an open socket alone. A non-`OK` reply fails the check.

### Added

- `\Redis`, `Predis\Client`, Memcached and Memcache clients are detected by subclass too (e.g. SncRedisBundle's phpredis client), including classes set through a parameter or inherited from a parent definition.
- `\RedisCluster` clients get a readiness check (`RedisClusterChecker`, label `Redis cluster connection`) doing the same `SET` probe.

### Documentation

- README: Redis section — detected clients, the write the probe needs, TLS configured in the client, Relay not supported.

## [1.1.0] - 2026-10-07

### Added

- Elastica 9 support: the readiness check and client detection work unchanged; CI runs the live-cluster test against Elasticsearch 9.5.5.

## [1.0.2] - 2026-10-06

### Fixed

- The Elastica readiness check fails when the cluster status is `red`; `green` and `yellow` still pass.

### Documentation

- README: per-check timeouts are checked after the probe returns; bound client-level timeouts for Elastica, Redis, PDO and HTTP clients.

## [1.0.1] - 2026-10-05

### Fixed

- Elastica 8 readiness check no longer fails with "Config key is not set: connections".
- Clients declared as a `ChildDefinition` of an abstract prototype (FOSElasticaBundle's `fos_elastica.client_prototype`) get a readiness check: the class is now resolved through the parent chain.
  The parent chain is followed for every auto-detected checker type (OpenSearch, ClickHouse, Kafka,
  Mailer, lock stores, cache pools, Elastica), so new readiness checks may appear after upgrading.
  Mark one as non-critical with `non_critical` or change its timeout with `timeouts.overrides`.
- Elastica 7 clients configured through top-level `host`, `url` or `servers` are probed instead of skipped.

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

[1.2.0]: https://github.com/msstc4symfony/healthcheck-bundle/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/msstc4symfony/healthcheck-bundle/compare/v1.0.2...v1.1.0
[1.0.2]: https://github.com/msstc4symfony/healthcheck-bundle/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/msstc4symfony/healthcheck-bundle/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/msstc4symfony/healthcheck-bundle/releases/tag/v1.0.0
