# CLAUDE.md

Guidance for Claude Code working in this repository. Deep references live
under `.claude/docs/`; this file stays lean.

## What this is

A Symfony bundle exposing Kubernetes-style **liveliness** and **readiness**
health checks. Targets PHP >= 8.4 and Symfony 6.4 / 7.x / 8.x. Library code
only — no host application in the repo.

PHP 8.4 is mandatory: the bundle uses asymmetric visibility, typed class
constants, property hooks, and `#[Override]` everywhere. Do not propose
backports.

## Common commands

All in `Makefile`:

- `make check` — full quality gate (lint, PHPStan level 9, php-cs-fixer,
  `composer validate --strict`, `composer audit`, Rector dry-run, DEPTRAC).
  CI runs the same pieces in parallel jobs with `PHPSTAN_CONFIG=phpstan-ci.neon`.
- `make fix` — apply `php-cs-fixer fix` then `rector process` writes.
- `make test` — `vendor/bin/phpunit` (unit + integration suites).
- `make test-with-coverage` — HTML coverage to `coverage/`.
- `make infection` — mutation testing (not in `make check` — too slow).
- `make regenerate-baseline` — regenerate `phpstan-baseline.neon` (only when
  intentionally accepting new findings).

Run a single test: `vendor/bin/phpunit --filter testRun tests/Unit/...`.
Run a single suite: `vendor/bin/phpunit --testsuite=unit` (or `integration`).

## Quality gate notes

- `phpunit.xml.dist` is **strict** (shared `bundle-standard` template):
  `failOnRisky`, `failOnWarning`, `failOnDeprecation` (own code only),
  `failOnPhpunitDeprecation`, `beStrictAboutOutputDuringTests`. Any new
  warning, deprecation, or stdout/stderr write fails the suite.
- PHPStan baseline entries are **not** added automatically. `make check`
  fails on new findings; use `make regenerate-baseline` only deliberately.
- Two composer manifests: `composer.json` (published) and `composer-ci.json`
  (full optional dev-deps, used in CI via `COMPOSER=composer-ci.json`).
- `make fix` is idempotent — cs-fixer and Rector both run, in that order.

## Architecture in 60 seconds

Three layers under `src/`, enforced by DEPTRAC:

- `Application/Health/Check/` — domain core (Action runners, DTOs,
  checkers, `CheckTypeEnum`).
- `Presentation/` — entry points (`HealthController` for
  `/_/healthcheck/{ping,readiness,liveliness}`, `healthcheck:liveliness` /
  `healthcheck:readiness` console commands).
- `DependencyInjection/` — extension + 3 compiler passes + 17 detectors.

Single execution path: `ActionInterface::run(Request) -> Response`. Action
runners (`Action` / `ParallelAction`) consume
`iterable<CheckInterface>` via `#[AutowireIterator(CheckInterface::class)]`.

**Checker discovery has two paths:** (1) user-supplied classes implementing
`CheckInterface` are auto-tagged via `#[AutoconfigureTag]`;
(2) `HealthCheckerAutoDetectionPass` discovers
`CheckerDetectorInterface` services (themselves tagged
`healthcheck.detector` via `#[AutoconfigureTag]`), runs each to synthesize
infrastructure-checker `Definition`s, and tags them `CheckInterface::class`.

Third-party bundles can contribute detectors by registering services
implementing `CheckerDetectorInterface`. No `HealthCheckBundle` subclass
needed.

## Pointers to deep references

Read these when the task touches the area:

- [`.claude/docs/architecture.md`](.claude/docs/architecture.md) — layer
  rules, compiler-pass chain, detector contract, AbstractReadinessChecker
  template, DTOs.
- [`.claude/docs/conventions.md`](.claude/docs/conventions.md) — PHP 8.4
  idioms, naming, file headers, test conventions, comment policy.
- [`.claude/docs/testing.md`](.claude/docs/testing.md) — unit vs
  integration suite split, TestKernel pattern, SafeEventDispatcher in
  tests, Infection scope.
- [`.claude/docs/tooling.md`](.claude/docs/tooling.md) — quality gate
  breakdown, baselines policy, DEPTRAC rules, Roave BC, two-manifest
  setup.
- [`.claude/docs/ci.md`](.claude/docs/ci.md) — workflow structure, matrix
  jobs, caches, action-version policy.
- [`.claude/docs/known-issues.md`](.claude/docs/known-issues.md) —
  gotchas, deferred work. **Check this before chasing a "weird"
  failure.**
