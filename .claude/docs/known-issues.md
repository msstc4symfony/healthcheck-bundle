# Known issues / quirks

Items that have bitten us once and might bite again. Living list — append,
don't churn.

## phpunit matrix conflicts with dev-only tools

Two dev-deps pin `symfony/console` ranges that block matrix variants:

- `roave/backward-compatibility-check` 8.x requires `symfony/console ^7.4.4`
  → blocks the Symfony 6.4 matrix entry.
- `deptrac/deptrac` 4.x requires `symfony/console ^6.4 || ^7.4 || ^8.0`
  → blocks any Symfony 7.0–7.3 minor.

Neither is exercised under the phpunit job (they have dedicated CI jobs
on the locked Symfony version). The phpunit matrix step removes both
before `composer update`:

```yaml
- name: Drop tools incompatible with the matrix Symfony version
  run: |
    composer remove --dev --no-update --no-interaction \
      roave/backward-compatibility-check \
      deptrac/deptrac
```

If you add a new dev-tool that pins a narrow Symfony range, either:
(a) ensure it supports all matrix variants, or (b) add it to this
removal list. Don't loosen the matrix to "the lowest common denominator
that satisfies dev tools" — the matrix tests the bundle's runtime
compatibility, not dev-tooling's.

## phpunit matrix — `browser-kit` / `dom-crawler` must match `http-kernel`

`Symfony\Component\HttpKernel\HttpKernelBrowser` extends
`Symfony\Component\BrowserKit\AbstractBrowser`. In Symfony 7+ the parent
gained a `: object` return type on `doRequest()`; the 6.4 child doesn't
declare it. If composer resolves browser-kit / dom-crawler to a higher
major than http-kernel (which happens by default — they have no
constraint locking them to the matrix-pinned major), PHP rejects the
class with a signature compatibility fatal during `WebTestCase`
autoload, manifesting as PHPUnit's "Premature end of PHP process".

The phpunit matrix step pins both packages alongside framework-bundle,
http-foundation, console. If you add another tightly-coupled
`symfony/*` pair, pin them too.

## phpunit matrix — `framework.handle_all_throwables`

Symfony 6.4 introduced a soft-deprecation asking apps to explicitly set
`framework.handle_all_throwables: true` because 7.0+ defaults to it. The
phpunit-bridge running under `SYMFONY_DEPRECATIONS_HELPER=max[direct]=0`
promotes the deprecation to a fatal during integration test boots.

`tests/integration/Kernel/TestKernel.php` sets the option explicitly
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
risky. Two mitigations in this project, both required:

1. `tests/bootstrap.php` defines `PHPUNIT_COMPOSER_INSTALL` before
   loading the autoloader. The constant is a signal to Symfony that
   PHPUnit is the runner; the framework skips the handler install.
2. `symfony/runtime` is a dev dependency (with its composer plugin
   disabled via `config.allow-plugins.symfony/runtime: false`).
   `FrameworkBundle::boot()` only registers an `ErrorHandler` if
   `SymfonyRuntime` class is absent — the dev-dep makes the absence
   branch unreachable.

Without both, integration tests are flagged risky. Don't simplify the
bootstrap.

## DEPTRAC composer name confusion

`qossmic/deptrac` was archived 2025-02-17. The active package is
`deptrac/deptrac` (new GitHub org, same project). Use
`deptrac/deptrac: ^4.6` in dev deps. The old package can't parse PHP 8.4
(property hooks / asymmetric visibility) because of its vendored
`nikic/php-parser`.

## Local `ext-mongodb` vs `mongodb/mongodb`

The local dev box may have an `ext-mongodb` version older than what
`mongodb/mongodb`'s composer constraint allows. `composer update` fails
on platform req. Workaround for local commands:
`--ignore-platform-req=ext-mongodb`. CI installs the right ext version,
no flag needed.

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
`tests/integration/Functional/` deliberately skip the JSON branch with a
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
