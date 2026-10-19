# Conventions

## PHP features in use

The bundle runs on PHP 8.2 (since 1.3.0; 1.0–1.2 required 8.4). Only 8.2 syntax;
8.4 functions (`array_any`) come from `symfony/polyfill-php84`.

- **Read-only for callers** without asymmetric visibility: `CheckResult`'s public
  arrays carry `@readonly` + `@phpstan-allow-private-mutation` — PHPStan rejects writes
  from outside, the class's own `add*()` / `resetTrailing()` may write. PHP itself does
  not enforce it. `Request` uses `public readonly`.
- **Class constants without a native type** (PHP 8.2 has none); interface constants
  (`CheckInterface::PROBE_KEY`, `CheckerDetectorInterface::TAG`) are not `final` (BC).
- **`Response::$success`** is a readonly property set in the constructor (was a property
  hook); cached responses use the `msstc4symfony_healthcheck.v2.` key prefix.
- **No anonymous readonly classes**: a test double extending a readonly class
  (`AbstractReadinessChecker`) is a named `final readonly` fixture in `tests/Mock/`.
- **`#[Override]`** on every implementation of a parent / interface method.
  Rector applies `AddOverrideAttributeToOverriddenMethodsRector`; respect it.
- **First-class callable syntax** (`self::method(...)`) is used in places
  (e.g., `CachedActionDecorator::canonicalize`); prefer it over
  `Closure::fromCallable`.

## File / namespace conventions

- Layout and family rules: stage B spec (`docs/superpowers/specs/2026-10-17-stage-b-conventions-design.md` at
  the msstc4symfony root) — canonical layers, `@internal` outside the Public API (majors only), `#[\Override]` everywhere.
- PSR-4 root: `Msstc4Symfony\HealthCheckBundle\` → `src/`.
- Test PSR-4 root: `Msstc4Symfony\HealthCheckBundle\Test\` → `tests/` (`Unit/`,
  `Integration/`, `Mock/`; layout shared by every bundle of `bundle-standard`).
- One class per file. Classes are `final readonly` unless extension is part
  of the contract (`AbstractReadinessChecker`).
- `#[Exclude]` on classes that are constructed by compiler passes as
  `Definition`s — prevents PSR-4 auto-registration from creating a duplicate
  generic service.

## File header

Two-line `<?php` + `declare(strict_types=1);` is the project standard.
Single-line `<?php declare(strict_types=1);` is **not** used. cs-fixer does
not enforce this directly — keep it consistent manually when adding files.

## Naming

- **Checkers**: `<Subject>Checker` (`DBALConnectionChecker`, `RedisChecker`).
- **Detectors**: `<Trigger>Detector` (`DBALConnectionDetector`,
  `CachePoolDetector`). The trigger is the container artifact the detector
  scans for (service id pattern, tag, class match, parameter).
- **Compiler passes**: `HealthChecker<Purpose>Pass`
  (`HealthCheckerAutoDetectionPass`, `HealthCheckerTimeoutDecorationPass`).
- **DTOs**: live under `Application/Health/Check/DTO/` and are
  `final readonly class`.
- **Constants**: `PROBE_KEY` style for public; private constants
  `SCREAMING_SNAKE`.

## Style tweaks beyond `@Symfony` cs-fixer preset

See `.php-cs-fixer.dist.php` for the full list. Notable overrides:
- `global_namespace_import` enabled → `use stdClass;` instead of
  `\stdClass`.
- Trailing commas required in multiline arguments / arrays / match /
  parameters.
- No yoda conditions.
- Left-aligned phpdoc.

Run `make fix` rather than hand-formatting. cs-fixer + Rector apply in
sequence; their fixes are idempotent.

## Test conventions

- Unit tests under `tests/Unit/`, integration under `tests/Integration/`.
  See `testing.md` for the suite split rationale.
- `final class` + `extends TestCase` (or `KernelTestCase` / `WebTestCase`
  for integration).
- One assertion per behavior; avoid single-test omnibus assertions.
- Use `#[DataProvider]` attributes for table-driven tests.
- Test method names: `test<Behavior>` — full sentences in camelCase
  describing what is being asserted, not what is being called.
- Mocks live under `tests/Mock/`. `SuccessChecker` / `FailChecker` /
  `RecordingDispatcherFixture` are reusable fixtures — prefer them over
  re-rolling mocks per test.

## Comments policy

- No tutorial-style comments (well-named identifiers do the job).
- Comment **WHY**, not WHAT. Specifically: hidden constraints, non-obvious
  invariants, workarounds tied to a specific incident or upstream bug, or
  surprising behavior a future reader would otherwise need to discover by
  experiment.
- Avoid referencing current task / fix / caller in comments — that belongs
  in the PR description and rots quickly.

## Configuration namespacing

All bundle parameters use the `msstc4symfony_healthcheck.` prefix. Public
constants (`DependencyInjection\ContainerIds::PARAM_*` / `SERVICE_*`) are the
single source of truth for parameter / service IDs.
