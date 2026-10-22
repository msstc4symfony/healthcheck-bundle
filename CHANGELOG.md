# Changelog

All notable changes to this bundle are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versions follow
[Semantic Versioning](https://semver.org/); dates are UTC.

## [2.1.0] - 2026-10-22

### Added

- `LabeledCheckerInterface::checkerLabel()`: implemented by `AbstractReadinessChecker` (its `label()`) and the
  bundle's decorators (the label of the checker they wrap); a checker or decorator of your own may implement it. A
  decorator of your own that implements only `CheckerDecoratorInterface` keeps the class in the messages below. A
  subclass of `AbstractReadinessChecker` that already declares a `checkerLabel()` must declare it
  `public function checkerLabel(): string`.

### Changed

- The timeout (`<checker> exceeded budget (…)`), non-critical (`<checker> failed (…)`) and parallel
  (`parallel: <checker> failed (…)`) failure messages name a checker that implements `LabeledCheckerInterface` — every
  built-in one — by its label, e.g. `Redis connection exceeded budget (…)`, instead of its class. Update alerts that
  match the class. Run events still carry the class, and an empty label or one that throws falls back to the class.

## [2.0.0] - 2026-10-20

No changes since 2.0.0-rc.1.

## [2.0.0-rc.1] - 2026-10-19

### Added

- README "Public API": the classes, configuration keys, routes and commands applications may rely on.

### Changed

- The 21 built-in checkers moved from `Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\<X>Checker`
  to `Msstc4Symfony\HealthCheckBundle\Infrastructure\Checker\<X>Checker` (`Cache`, `ClickHouse`, `DBALConnection`,
  `DoctrineMigrations`, `ElasticaConnection`, `EntityManager`, `Flysystem`, `HttpClient`, `Kafka`, `LockStore`,
  `Mailer`, `Memcache`, `Memcached`, `MessengerTransport`, `MongoConnection`, `ODMConnection`, `OpenSearch`, `Predis`,
  `Rabbitmq`, `Redis`, `RedisCluster`) — update service definitions and decorations that name them.
  `CheckInterface`, `AbstractReadinessChecker`, `CheckerDecoratorInterface` and the decorators stay in place.
- `Msstc4Symfony\HealthCheckBundle\DependencyInjection\HealthChecker{AutoDetection,CriticalityDecoration,DeferredConstruction,TimeoutDecoration}Pass`
  → `Msstc4Symfony\HealthCheckBundle\DependencyInjection\CompilerPass\…`.
- `Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\HttpProbeTarget` →
  `Msstc4Symfony\HealthCheckBundle\Infrastructure\Checker\HttpProbeTarget`.
- Run events (`HealthCheckerCompletedEvent::$checkerClass`) and the timeout, non-critical and parallel failure
  messages name the new FQCNs of the built-in checkers — update listeners, metric labels or alerts matching the old.
- Classes outside the README "Public API" are `@internal` (Action runners, `CachedActionDecorator`,
  `CredentialRedactor`, `SafeEventDispatcher`, the built-in decorators, `DependencyInjection\*` except the two detector
  interfaces, `Presentation\*`).
- PHPStan enforces `#[\Override]` on every overriding or implementing method (`checkMissingOverrideMethodAttribute`).
- Configuration keys, routes and console commands are unchanged.
- Adopts bundle-standard v3 (canonical hex layout, anchored deptrac `BundleRoot`).

## [1.3.0] - 2026-10-14

### Added

- `symfony/polyfill-php84` (`array_any` on PHP 8.2/8.3) and `symfony/var-exporter` dependencies.

### Changed

- Requires PHP 8.2 instead of 8.4 (Symfony 8 still needs PHP 8.4).
- `Request::$type` / `$options` are `public readonly` (were `public private(set)`); reading is unchanged.
- `CheckResult::$messages` / `$errors` / `$warnings` are plain public properties marked `@readonly` for
  PHPStan: PHP 8.2 has no asymmetric visibility, so only static analysis rejects writes from outside.
- `Response` is a `final readonly` class; `$success` is set in the constructor instead of a property hook.
  Cached responses use the key prefix `msstc4symfony_healthcheck.v2.`: entries cached by 1.0–1.2 are ignored.
- The label of a checker whose target fails to construct is read through `VarExporter\Instantiator` instead
  of a PHP 8.4 lazy ghost; it now also works when the checker inherits its constructor.
- Class constants no longer declare a native type (PHP 8.2 has none); values are unchanged.

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

[2.1.0]: https://github.com/msstc4symfony/healthcheck-bundle/compare/v2.0.0...v2.1.0
[2.0.0]: https://github.com/msstc4symfony/healthcheck-bundle/compare/v2.0.0-rc.1...v2.0.0
[2.0.0-rc.1]: https://github.com/msstc4symfony/healthcheck-bundle/compare/v1.3.0...v2.0.0-rc.1
[1.3.0]: https://github.com/msstc4symfony/healthcheck-bundle/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/msstc4symfony/healthcheck-bundle/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/msstc4symfony/healthcheck-bundle/compare/v1.0.2...v1.1.0
[1.0.2]: https://github.com/msstc4symfony/healthcheck-bundle/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/msstc4symfony/healthcheck-bundle/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/msstc4symfony/healthcheck-bundle/releases/tag/v1.0.0
