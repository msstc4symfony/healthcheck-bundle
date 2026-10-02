# Testing

## Test suites

`phpunit.xml.dist` declares two suites; both run under `make test`:

| Suite | Path | Purpose |
|-------|------|---------|
| `unit` | `tests/Unit/` | Pure `TestCase`, no kernel. Fast (~0.4 s wall time). |
| `integration` | `tests/Integration/` | Boots `TestKernel`, exercises wiring + HTTP + CLI. ~2 s. |

Run a single suite: `vendor/bin/phpunit --testsuite=unit` or
`--testsuite=integration`. Run a single test:
`vendor/bin/phpunit --filter testRun tests/Unit/...`.

`phpunit.xml.dist` is **strict** — `failOnRisky`, `failOnWarning`,
`failOnPhpunitDeprecation`, `beStrictAboutOutputDuringTests`. Any new
warning, deprecation, or stdout/stderr write fails the suite.

## TestKernel pattern

`tests/Integration/Kernel/TestKernel.php` uses Symfony's
`MicroKernelTrait`. It registers only `FrameworkBundle` + `HealthCheckBundle`
and:

- Disables `framework.php_errors.log` — the default `true` installs a global
  error handler that survives kernel shutdown and trips `failOnRisky`.
- Overrides `logger` with `Psr\Log\NullLogger` — the Symfony default writes
  to stdout/stderr and trips `beStrictAboutOutputDuringTests`.
- Loads controller routes via attribute resource pointing at
  `src/Presentation/Controller/`.

For HTTP functional tests, **override `createKernel()`** to force
`debug=false`. Debug mode activates Symfony's debug logger and the
`DebugHandlersListener`, which both interact badly with strict PHPUnit.

PHPUnit's own binary defines `PHPUNIT_COMPOSER_INSTALL`, which tells
`Kernel::initializeContainer()` not to install its deprecation-collecting
error handler; no custom bootstrap is needed (`phpunit.xml.dist` is the shared
`bundle-standard` template, bootstrap `vendor/autoload.php`).

## Minimal install (no optional libraries)

CI job "PHPUnit without optional libraries" (bundle-standard since v1.7) installs
only `composer.json` (no `composer-ci.json` extras: doctrine/*, symfony/lock,
mailer, messenger, browser-kit, http-client, predis, elastica, ...) and runs
`vendor/bin/phpunit`. Every test needing an optional package must SKIP there:

- Whole-class dependency → `setUp()` guard
  `if (!class_exists(X::class)) { self::markTestSkipped('vendor/pkg is not installed'); }`.
- Only some methods depend on it (detector/pass tests where exact-class-name
  matching works without the library, subclass matching does not) →
  `#[RequiresMethod(PersistingStoreInterface::class, 'save')]` per method.
- Named fixture types extending optional types must live in their own file
  (`tests/Mock/...`, e.g. `CountableMessengerTransportFixture`) — declared at
  test-file scope they fatal on file load and abort the whole run.

Local repro: rsync repo (without vendor/.git) to `$TMPDIR/minimal/...`,
`rm composer.lock`, `composer update`, run `vendor/bin/phpunit` (2026-10-01 UTC:
230 tests, 78 skipped; 2026-10-02 UTC: 295 tests, 87 skipped). Note `--do-not-record-test-run-history` exits 1 there
because `executionOrder="depends,defects"` emits a runner warning.

## SafeEventDispatcher in tests

Tests wishing to assert event emission should:

```php
$inner = new RecordingDispatcherFixture();
$action = new Action($checkers, new SafeEventDispatcher($inner));
// assert against $inner->events
```

Don't pass an `EventDispatcherInterface` directly; the action constructor
takes `SafeEventDispatcher` (positive default: `new SafeEventDispatcher()`
which is a silent no-op).

## Symfony deprecation tracking

`phpunit.xml.dist` sets `failOnDeprecation="true"` with
`<source ignoreIndirectDeprecations="true">`: a deprecation triggered by this
bundle's code fails the suite, deprecations raised inside dependencies do not.
This replaced `symfony/phpunit-bridge` + `SYMFONY_DEPRECATIONS_HELPER=max[direct]=0`
(2026-10-01 UTC) and is the same in every bundle.

## Roave BC check baseline

The CI `bc-check` job runs `roave/backward-compatibility-check` against
the latest stable tag and is blocking since `bundle-standard` v1.8.0 (adopted
in v1.3.0): every change in a 1.x release must be additive or a bug fix.

Local probe (mind the xdebug interference — disable it):

```bash
php -d xdebug.mode=off vendor/bin/roave-backward-compatibility-check \
    --from=v1.2.0 --to=HEAD --format=console
```

## Mutation testing (Infection)

`infection.json5` config:
- Sources: `src/`
- Test framework: phpunit, restricted to `--testsuite=unit` (integration
  tests boot a kernel — too slow per-mutant).
- Logs: `var/infection/{infection.log,infection.json}` (gitignored via
  `/var/`).

Run: `make infection`. ~8 s wall time on this codebase.

CI job runs **push-to-main only** and fails below the `infection-min-msi` /
`infection-min-covered-msi` inputs of `checks.yml` (72 / 72; MSI 79.9 % at
v1.3.0). Locally: `make infection` plus `--min-msi=72 --min-covered-msi=72`.

## DEPTRAC

`deptrac.yaml` declares 4 layers — see `architecture.md`. Run:
`vendor/bin/deptrac analyse --config-file=deptrac.yaml`. Integrated into
`make check`.

If a new structural dependency is needed, prefer **refactoring to respect
the layer rule** over loosening the rule. The rule list is intentionally
tight.

## Coverage

`make test-with-coverage` produces HTML coverage in `coverage/`. CI
phpunit job uploads clover XML to Codecov on the default Symfony variant
only (one upload per CI run — flags don't differentiate matrix variants).
