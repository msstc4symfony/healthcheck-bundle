# Conventions

## PHP 8.4 features in use

The bundle hard-requires PHP 8.4. Don't propose downgrades.

- **Asymmetric visibility** (`public private(set) array $messages`) on
  `CheckResult` and `Request` — accumulators that are public-read but only
  mutable through the class's own setters.
- **Typed class constants** (`public const string TAG = ...`) on
  interface contracts (`CheckInterface::PROBE_KEY`, `CheckerDetectorInterface::TAG`).
- **Property hooks** on `Response::$success` (computes from `errors`).
- **`#[Override]`** on every implementation of a parent / interface method.
  Rector applies `AddOverrideAttributeToOverriddenMethodsRector`; respect it.
- **First-class callable syntax** (`self::method(...)`) is used in places
  (e.g., `CachedActionDecorator::canonicalize`); prefer it over
  `Closure::fromCallable`.

## File / namespace conventions

- PSR-4 root: `Msstc4Symfony\HealthCheckBundle\` → `src/`.
- Test PSR-4 root: `Msstc4Symfony\HealthCheckBundle\Test\` → `tests/` (`Unit/`,
  `Integration/`, `Mock/`; layout shared by every bundle, `bundle-standard` 1.7+).
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
constants (`HealthCheckExtension::PARAM_*` / `SERVICE_*`) are the
single source of truth for parameter / service IDs.
