# Known issues / quirks

Items that have bitten us once and might bite again. Living list — append,
don't churn.

## phpunit matrix — now owned by `bundle-standard`

The matrix job lives in the shared reusable workflow (`bundle-standard`
`php-bundle.yml`). Two quirks first found here are handled there for every
bundle:

- `roave/backward-compatibility-check` and `deptrac/deptrac` pin narrow
  `symfony/console` ranges; the matrix job removes both before resolving.
- `browser-kit` / `dom-crawler` must match `http-kernel` (a 7+ parent with a
  6.4 child fatals `WebTestCase` with a signature mismatch). Since v1.6.0 the
  job pins every lockstep `symfony/*` package listed in `composer-ci.json`,
  adds `dom-crawler` whenever `browser-kit` is listed, and fails a cell whose
  resolved `http-kernel` major differs from its label.

Adding a new dev tool with a narrow Symfony range: fix it in
`bundle-standard`, not here.

## Auto-detection skips abstract service templates

Recent FrameworkBundle releases (6.4.x latest, 7.4, 8.1) register abstract
templates such as `lock.store.combined.abstract`. Detectors match by class, so
a checker pointing at one failed container compilation ("reference to an
abstract definition"). `HealthCheckerAutoDetectionPass` now drops any detected
checker whose arguments reference an abstract definition (aliases resolved).
`HttpClientTargetDetector` is exempt: its targets come from explicit config, and a
bad `client` reference there must fail compilation rather than silently drop the probe. The lock file pinned
an older FrameworkBundle (roave/backward-compatibility-check caps
`symfony/console`), so only the CI matrix — which removes roave and resolves
the latest framework — exposed it.

## Lock store check names contain the container hash (open)

FrameworkBundle registers lock stores as hidden `.lock.<resource>.store.<hash>`
services, so the checker id is `healthcheck.checker..lock.default.store.<hash>`
and the label `Lock store (.lock.default.store.<hash>)`. The hash follows the DSN,
so dashboards keyed by the check name break when the DSN changes. Not fixed yet:
a combined store yields several hidden services per resource (stripping the hash
collides) and the DSN may contain credentials (unusable as a label). Candidate
fix: `lock.<resource>` plus a positional index for combined stores.

## PHPUnit deprecations from `MemcacheCheckerTest` (pecl memcache)

Stubbing `Memcache` makes PHPUnit generate a class from the extension's
signatures, which use implicitly nullable parameters — deprecated since PHP 8.4.
The 38 deprecations in CI come from the extension, not the bundle; they do not
fail the run. They disappear when pecl memcache ships explicit nullable types.

## Symfony 8 — `KernelTestCase::runCommand()` is now a static method

A private `runCommand()` helper in a `KernelTestCase` subclass fatals on
Symfony 8 ("Cannot make static method ... non static"). The helper in
`HealthCommandFunctionalTest` is `executeCommand()`.

## `symfony/yaml` is a runtime dependency

`HealthCheckExtension` loads `services.yaml` through `YamlFileLoader`. Until
v1.1.0 `symfony/yaml` was not declared and only arrived transitively; an
app without it failed at container build. `bundle-standard` v1.5.0 enforces it.

## phpunit matrix — `framework.handle_all_throwables`

Symfony 6.4 introduced a soft-deprecation asking apps to explicitly set
`framework.handle_all_throwables: true` because 7.0+ defaults to it.

`tests/Integration/Kernel/TestKernel.php` sets the option explicitly
(harmless on 7+/8+).

## Roave BC check + `roave/security-advisories` recursion

Running `roave-backward-compatibility-check` locally with xdebug enabled
hits a stack-overflow inside Composer's `SecurityAdvisoryPoolFilter` when
resolving `roave/security-advisories`'s very large constraint set.

Workaround: `php -d xdebug.mode=off vendor/bin/roave-backward-compatibility-check ...`

CI runs without xdebug on the `bc-check` job, so it's a local-only
nuisance. Don't add memory_limit / max_nesting_level workarounds to the
Makefile — fix the env.

## PHPUnit `failOnRisky` and Symfony Kernel

By default `Kernel::initializeContainer()` installs an error handler under
debug mode that survives kernel shutdown — PHPUnit reports the test as
risky. Mitigations:

1. PHPUnit's binary defines `PHPUNIT_COMPOSER_INSTALL`; Symfony then skips the
   handler install. (The former `tests/bootstrap.php` defined it too and was
   removed as redundant on 2026-10-01 UTC.)
2. `symfony/runtime` is a dev dependency (with its composer plugin
   disabled via `config.allow-plugins.symfony/runtime: false`).
   `FrameworkBundle::boot()` only registers an `ErrorHandler` if
   `SymfonyRuntime` class is absent — the dev-dep makes the absence
   branch unreachable.

## DEPTRAC composer name confusion

`qossmic/deptrac` was archived 2025-02-17. The active package is
`deptrac/deptrac` (new GitHub org, same project). Use
`deptrac/deptrac: ^4.6` in dev deps. The old package can't parse PHP 8.4
(property hooks / asymmetric visibility) because of its vendored
`nikic/php-parser`.

## Local `ext-mongodb` vs `mongodb/mongodb`

`composer-ci.json` pins `config.platform.ext-mongodb` to the CI runner's
extension (2.5.2), so `composer-ci.lock` resolves `mongodb/mongodb` 2.x and
installs on a dev box with an older extension without flags. Tests touching
the driver need a matching extension locally.

## `_format=json` does not switch probe response

`HealthController::formatOutput()` checks `$request->getRequestFormat()`,
which reads from request **attributes** (set by the router from route
attribute `_format` or path placeholder). `_format` as a query parameter
or in the path extension (`.json`) is NOT auto-translated to the request
format without an attribute on the route.

The bundle's `#[Route]` attributes don't declare `_format`. As a result,
the JSON formatting path can only be exercised via direct
`$request->setRequestFormat('json')` — which is what the unit
`HealthControllerTest` does. Functional tests under
`tests/Integration/Functional/` deliberately skip the JSON branch with a
doc comment explaining this.

If a host application wants JSON probes over HTTP, they must wire
content-negotiation themselves (e.g., a `kernel.request` listener that
sets the format from `Accept`).

## `CacheChecker` skip semantics changed in v1.1.0 (forthcoming)

Pre-`v1.1.0`, `CacheChecker` with `NullAdapter` or APCu-in-CLI emitted
"<label> passed" — a false success signal. From v1.1.0 onward it emits
"<label> skipped (NullAdapter)" / "skipped (APCu in CLI without
apc.enable_cli)".

This is a deliberate behaviour change. Operational dashboards that match
on the `passed` substring will need their queries updated.

## `composer.json` `version` field removed

`composer validate --strict` flags an explicit `version: "1.0.0"` as a
warning when the package is published on Packagist (where versions are
derived from git tags). We dropped the field from both `composer.json`
and `composer-ci.json`. Don't add it back when copy-pasting from older
references.

## DI extension alias renamed `maxshamaev_healthcheck` → `msstc4symfony_healthcheck`

The Symfony DI extension alias (`HealthCheckExtension::ALIAS`), all
`PARAM_*`/`SERVICE_*` container id constants, the `TreeBuilder` root
name in `Configuration.php`, and the `CachedActionDecorator` cache-key
prefix were renamed from `maxshamaev_healthcheck` to
`msstc4symfony_healthcheck` to match the `Msstc4Symfony\*` namespace
(the alias is snake_case, so it survived the earlier namespace/vendor
rename untouched).

**Breaking for consumers**: any `config/packages/maxshamaev_healthcheck.yaml`
or `maxshamaev_healthcheck:` config key stops being recognized — must be
renamed to `msstc4symfony_healthcheck`. Cached results under the old
cache-key prefix are simply orphaned (new prefix, no migration needed).

## Deferred / on-demand work

Tracked here so it doesn't get forgotten.

- **Per-version PHPStan baselines for the Symfony matrix.** PHPStan
  currently runs once against the locked Symfony version. If `Phpunit`
  matrix surfaces Symfony 6.4 / 7 deprecations, those won't fail
  PHPStan. Tradeoff: matrix-aware PHPStan would multiply CI time × 3.
- **Roave BC check promotion.** Flip `continue-on-error: false` after
  tagging `v1.1.0`.
- **Infection min-MSI threshold.** Currently informational. Set
  `--min-msi` in the Makefile target after observing a stable baseline
  (current baseline: 66.74 %).
- **`prefer-lowest` matrix variant.** Would catch missing lower bounds in
  composer constraints; not currently wired.
- **PHP version matrix.** Currently single 8.4. Add 8.5+ when those land.
