# Changelog

## 1.3.1

### Fixed

- A `framework.lock` store wrapping an already probed connection (1.3.0) is skipped only while a critical
  checker still probes that connection, and never when its own checker id is listed in `non_critical` or
  `timeouts.overrides`. In 1.3.0 the store disappeared even then: its configuration was silently ignored,
  and with a `non_critical` connection a lock-store outage no longer failed readiness.
- `DBALConnectionChecker` always runs the platform's dummy `SELECT`: a connection that reports itself
  connected after the server went away (long-running workers) passed readiness.
- A checker declared as a child of a `parent` service: a named argument (`$name`) of the child overrides
  the parent's positional one in the failure label too; circular `parent` chains no longer exhaust memory
  at container build (Symfony reports the cycle).

## 1.3.0

### Fixed

- A `non_critical` checker whose `check()` throws reports a warning (`<checker class> failed (<message>)`,
  credentials masked) instead of the exception escaping the sequential runner (HTTP 500 / command crash)
  or failing readiness as `parallel: X failed (…)` in the parallel runner. Critical checkers are unchanged.
- `DBALConnectionChecker` works with Doctrine DBAL 3: it called `Connection::getServerVersion()`, which is
  private before DBAL 4, so a not-yet-connected DBAL 3 connection always failed readiness. It now runs the
  platform's dummy `SELECT` to connect.
- A checker declared as a child of an abstract `parent` service is recognised as readiness-only (its class
  is resolved through the parent chain): it is built inside the readiness probe, so a client that cannot
  be constructed no longer breaks liveliness.
- When `framework.lock` names a connection service the bundle already probes (a `\Redis` client, a DBAL
  connection, …), the lock store FrameworkBundle builds around it is no longer probed a second time.

### Changed

- Lock stores built by FrameworkBundle are labelled by their resource: `Lock store (lock.default)`,
  `Lock store (lock.invoice[1])` for the second store of a combined resource, instead of
  `Lock store (.lock.default.store.<hash>)`. Dashboards matching the old text need updating. The checker
  ids (`healthcheck.checker..lock.<resource>.store.<hash>`, the keys of `non_critical` / `timeouts`) are
  unchanged.
- Dependencies: `symfony/error-handler` releases that use `E_STRICT` on PHP 8.4 conflict
  (`<6.4.10`, `7.0.0`–`7.0.9`, `7.1.0`–`7.1.2`); PHPUnit `>=11.5` for development.

### Internal

- `bundle-standard` v1.8.0: blocking Roave BC check, blocking Infection (min MSI / covered MSI 72 %), a
  `--prefer-lowest` PHPUnit cell; PHPStan level 10.

## 1.2.0

### Added

- JSON probe output over HTTP: `?_format=json` or an `Accept: application/json` header (a route-level
  `_format` still wins, then the query parameter, then the header). Text stays the default.
- Text output of `/_/healthcheck/{readiness,liveliness}` ends with a `Warnings:` section (failures of
  `non_critical` checkers); the console commands print `Warnings:` when there are any.
- Probe responses carry `Vary: Accept`.

### Fixed

- `HealthCheckerCompletedEvent::$checkerClass` (and the `parallel: X failed (…)` message) names the
  real checker class again instead of the bundle's `TimeoutCheckerDecorator` /
  `DeferredReadinessCheckerDecorator` / `NonCriticalCheckerDecorator`.
- `ParallelAction` masks credentials in `parallel: X failed (…)` messages, like every other failure
  message since 1.1.3.

### Changed

- Readiness probes only cache pools backed by infrastructure (Redis/Valkey, Memcached, PDO,
  Doctrine DBAL, Couchbase, third-party adapters, chains containing one of them). Pools in memory or
  on the local disk are no longer listed — FrameworkBundle's `cache.system`, `cache.validator`,
  `cache.serializer`, `cache.property_info`, expression-language pools, and a filesystem `cache.app`.
  Dashboards matching those check names lose them; `healthcheck.checker.cache.pool.<name>` ids of
  local pools no longer exist; entries for them in `non_critical` / `timeouts` are ignored. To keep
  probing a local pool (e.g. a filesystem cache on a shared volume), register a `CacheChecker` for it
  (see README, "Probing a Local Cache Pool").

### Documentation

- README: the custom-checker example uses `CheckResult::addMessage()` / `addError()` and the real
  `CheckInterface` tag (it showed `healthcheck.checker` and direct array writes, which do not work).

## 1.1.3

### Security

- Failure messages no longer expose credentials: user info in URLs (`redis://user:pass@host` →
  `redis://***@host`) and secret query parameters (`password`, `token`, `api_key`, …) are masked
  in readiness output — client factories such as Lock's `StoreFactory` quote the full DSN.

### Changed

- A readiness checker whose client cannot be constructed now reports under the checker's own
  label (`Lock store (.lock.default.store.<hash>) failed (…)`) instead of its service id
  (`healthcheck.checker..lock.default.store.<hash> failed (…)`).

### Known limitations

- `HealthCheckerCompletedEvent::$checkerClass` names the outermost decorator, not the real
  checker class (see 1.1.2); fixed in 1.2.0.

## 1.1.2

### Fixed

- Liveliness no longer fails when a readiness dependency cannot be constructed (e.g.
  `SemaphoreStore` without ext-sysvsem threw while the checkers were instantiated, returning
  HTTP 500 on `/_/healthcheck/liveliness`). Readiness-only checkers are now built inside the
  readiness probe, and a construction failure becomes that check's failure.
- Lock store detection probes only the stores of configured `framework.lock` resources and the
  application's own stores, no longer FrameworkBundle 8.1's predefined `.lock.flock.store` /
  `.lock.semaphore.store`.

### Changed

- `HealthCheckerCompletedEvent::$checkerClass` is `DeferredReadinessCheckerDecorator` for
  readiness-only checkers (was `TimeoutCheckerDecorator`).

## 1.1.1

### Fixed

- Container compilation crashed (`ClassNotFoundError`) in applications containing a service
  whose class extends a class from a package that is not installed — e.g. Symfony 8.1 with
  security-bundle but without symfony/validator (`UserPasswordValidator`). Detectors now check
  service classes through container reflection; classes given as `%parameters%` are detected too.

### Changed

- Tests follow the shared `bundle-standard` 1.7 layout and configuration; CI also runs the suite
  without optional libraries.

## 1.1.0

First release under `msstc4symfony/healthcheck-bundle` / `Msstc4Symfony\HealthCheckBundle`
(previously `MaxShamaev\HealthCheckBundle`).

### Changed

- The configuration root is `msstc4symfony_healthcheck` (was `maxshamaev_healthcheck`):
  rename `config/packages/maxshamaev_healthcheck.yaml` and its root key.
- `symfony/yaml` is now required (the extension loads `services.yaml`; it used to
  arrive only transitively).
- CI runs the shared `bundle-standard` gate on PHP 8.4/8.5 × Symfony 6.4/7.4/8.x with
  every checker's client extension installed.

### Fixed

- Container compilation failed on recent FrameworkBundle releases (6.4.x latest, 7.4, 8.1),
  which register abstract templates such as `lock.store.combined.abstract`; auto-detected
  checkers now skip abstract services. Explicitly configured HTTP targets still fail loudly
  on a bad client reference.
- Test suite compatibility with Symfony 8 (`KernelTestCase::runCommand()` became static).
