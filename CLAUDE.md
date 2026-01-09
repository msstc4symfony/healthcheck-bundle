# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Symfony bundle that exposes Kubernetes-style **liveliness** and **readiness** health checks. Targets PHP >= 8.1 and Symfony 6.4 / 7.x / 8.x. Distributed as a `symfony-bundle` Composer package; there is no host application in the repo — everything is library code plus unit tests.

## Common commands

All workflow targets live in the `Makefile`:

- `make check` — full quality gate: `php -l` lint, `phpstan` (level 9, uses `phpstan-baseline.neon`), `php-cs-fixer check`, `composer audit`, and `rector process -n` (dry run). CI runs the same target.
- `make fix` — apply `php-cs-fixer fix` then `rector process` writes.
- `make test` — `vendor/bin/phpunit`.
- `make test-with-coverage` — phpunit with HTML coverage in `coverage/`.
- `make regenerate-baseline` — regenerate `phpstan-baseline.neon` (only when intentionally accepting new findings).

Run a single test: `vendor/bin/phpunit --filter testRun tests/unit/Application/Health/Check/ActionTest.php`.

`phpunit.xml.dist` is **strict** — `failOnRisky`, `failOnWarning`, `failOnPhpunitDeprecation`, and `beStrictAboutOutputDuringTests` are all enabled, so any new warning/deprecation/output breaks the suite.

## Architecture

The codebase splits into three layers under `src/`:

- `Application/Health/Check/` — orchestration, DTOs, checkers, and the `CheckTypeEnum` (`READINESS` / `LIVELINESS`).
- `Presentation/` — entry points: `Controller/HealthController` exposes `GET /_/healthcheck/{ping,readiness,liveliness}` (200 on success, 406 on failure; JSON or plain text based on request format). `Command/AbstractHealthCommand` + `HealthLivelinessCommand` / `HealthReadinessCommand` provide the CLI commands `healthcheck:liveliness` (alias `healthcheck`) and `healthcheck:readiness`.
- `DependencyInjection/HealthCheckExtension` — loads `Resources/config/services.yaml` and also implements `CompilerPassInterface`.

The single execution path is `Action::run(Request) -> Response`:

1. `Action` receives `iterable<CheckInterface>` via `#[AutowireIterator(CheckInterface::class)]` — every checker tagged with `CheckInterface::class` flows in.
2. For each checker, `isSupport(Context)` filters by `CheckTypeEnum` (a checker can opt in to readiness, liveliness, or both).
3. Selected checkers mutate a shared `CheckResult` (`messages[]` / `errors[]`) via `check()`.
4. The final `Response` has `success = (errors === [])`.

**Auto-registration of checkers is the load-bearing part.** `CheckInterface` carries `#[AutoconfigureTag(CheckInterface::class)]`, so any user-defined implementor in an autoconfigured service path is automatically tagged. On top of that, `HealthCheckExtension::process()` scans the *container* and synthesizes checkers for every detected infrastructure service:

- `doctrine.dbal.*_connection` → `DBALConnectionChecker`
- `doctrine_mongodb.odm.*_connection` → `MongoConnectionChecker`
- services tagged `old_sound_rabbit_mq.connection` → `RabbitmqChecker`
- definitions whose class is `Predis\Client` / `Redis` / `Memcached` / `Memcache` → matching client checker
- services tagged `cache.pool` (walking `ChildDefinition` parents to recover the real class) → `CacheChecker`
- definitions whose class is `\Elastica\Client` → `ElasticaConnectionChecker`

Every synthesized definition is autowired and re-tagged with `CheckInterface::class`, so it joins the same iterator `Action` consumes. When adding a new infrastructure checker, follow this pattern: implement `CheckInterface`, then either rely on autoconfigure (user-supplied services) or add a `defined*Checkers()` scan in `HealthCheckExtension::process()` for services that need detection from the container.

`services.yaml` PSR-4-loads everything under the bundle namespace **except** `DependencyInjection/` (loaded by Symfony itself) and `Application/Health/Check/Checker/` (registered explicitly by the compiler pass to avoid double-registration of auto-detected ones).

## Code-quality config worth knowing

- **PHPStan**: `level: 9`, `treatPhpDocTypesAsCertain: false`, with `spaze/phpstan-disallowed-calls`, `phpstan-strict-rules`, `phpstan-symfony`, `phpstan-doctrine`, `phpstan-beberlei-assert`. New issues are *not* auto-baselined — `make check` will fail; use `make regenerate-baseline` only when you have a deliberate reason.
- **PHP-CS-Fixer**: `@Symfony` preset with project tweaks (left-aligned phpdoc, `global_namespace_import` → import classes, trailing commas in multiline arguments/arrays/match/parameters, post-increment style, no yoda conditions). Run `make fix` rather than hand-formatting.
- **Rector**: PHP 8.1 sets plus prepared sets for deadCode/codeQuality/codingStyle/typeDeclarations/privatization/instanceOf/earlyReturn and Symfony+Doctrine quality. A handful of rectors are explicitly skipped (see `rector.php`) — respect those when refactoring.
