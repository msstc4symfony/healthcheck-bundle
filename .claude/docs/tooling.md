# Tooling

## Quality gate (`make check`)

Runs sequentially. Any non-zero exit kills the gate.

| Tool | Config | Purpose |
|------|--------|---------|
| `php -l` | — | Syntax lint |
| `phpstan` | `phpstan.dist.neon` (local) / `phpstan-ci.neon` (CI) | Static analysis, level 10 |
| `php-cs-fixer check` | `.php-cs-fixer.dist.php` | Coding style |
| `composer validate --strict --no-check-publish` | — | Manifest schema + lock sync |
| `composer audit` | — | Security advisories |
| `rector process -n` | `rector.php` | Dry-run code-modernization check |
| `deptrac analyse` | `deptrac.yaml` | Layer rule enforcement |

CI parallelises these as independent jobs (see `ci.md`). Locally
sequential is fine — the codebase is small.

## PHPStan

- **Level 10**, `treatPhpDocTypesAsCertain: false`.
- Extensions: `spaze/phpstan-disallowed-calls`, `phpstan-strict-rules`,
  `phpstan-symfony`, `phpstan-doctrine`, `phpstan-beberlei-assert`.
- Baseline: `phpstan-baseline.neon`. New findings are **not** auto-baselined
  — `make check` will fail. Use `make regenerate-baseline` only when
  intentionally accepting accumulated drift.
- CI variant (`phpstan-ci.neon`) extends `phpstan.dist.neon` with
  `reportUnmatchedIgnoredErrors: false` — local baseline entries for
  optional libraries don't break CI where those libraries are installed.
  New findings still fail.

## PHP-CS-Fixer

`@Symfony` preset with project tweaks:
- `global_namespace_import` → import classes
- Trailing commas in multiline arguments / arrays / match / parameters
- Post-increment style
- No yoda conditions
- Left-aligned phpdoc

`make fix` applies fixes; `make check` only verifies. Don't hand-format.

## Rector

PHP 8.4 set + prepared sets for
deadCode/codeQuality/codingStyle/typeDeclarations/privatization/instanceOf/earlyReturn,
plus Symfony+Doctrine quality. A small skip list (see `rector.php`) avoids
specific rules that fight the project's idioms — respect those when
refactoring.

`make check` runs Rector in dry-run mode and fails on any pending change.
`make fix` actually applies them.

## DEPTRAC

4 layers (`Application`, `Presentation`, `DependencyInjection`,
`BundleRoot`) enforced via `deptrac.yaml`. See `architecture.md` for the
rule matrix.

If `make check` reports DEPTRAC violations, **fix the structural
dependency**, don't relax the rule. The rules are tight on purpose — the
3-layer split is load-bearing.

## Composer manifests

There are two:

- `composer.json` — minimal runtime + dev tooling. Used by the published
  Packagist artifact.
- `composer-ci.json` — same runtime, plus all optional infrastructure
  dev-deps (Doctrine ORM, Predis, Elastica, OpenSearch, RabbitMQ, etc.) so
  PHPStan can resolve every checker, and integration tests run.

`composer-ci.json` also declares `conflict` `symfony/error-handler: <7.4.17 || >=8.0,<8.1.5`
(CI-only, the published manifest does not have it): older error-handler releases leave their
exception handler registered after `ErrorHandler::register()` under PHPUnit's handler, which
`failOnRisky` turns into risky kernel tests on the prefer-lowest cell. Same guard in every
bundle of the family; the `symfony/*` pin does not touch `conflict`.

Both have lockfiles. CI sets `COMPOSER=composer-ci.json` to switch.
Locally, the default `composer install` uses `composer.json`; to test
against the full set, prefix commands with `COMPOSER=composer-ci.json`.

`composer-ci.json` `config.allow-plugins`:
- `phpstan/extension-installer: true`
- `infection/extension-installer: true`
- `composer/package-versions-deprecated: true`
- `symfony/runtime: false` — the package is installed for its
  `SymfonyRuntime` class (FrameworkBundle uses it to skip its own error
  handler registration during tests), but we don't want its plugin to run.

## Backward-compat tracking

`roave/backward-compatibility-check` runs in CI as the blocking `bc-check`
job against the latest stable tag.

When tagging a new release, run locally first to capture BC changes for the
CHANGELOG:

```bash
php -d xdebug.mode=off vendor/bin/roave-backward-compatibility-check \
    --from=v<previous> --to=HEAD --format=markdown
```

## Mutation testing

See `testing.md` — Infection runs only via `make infection` and the CI
push-to-main `infection` job. Not part of `make check` (too slow for
inner-loop).

## Updating dependencies

`composer audit` runs on every CI build and is part of `make check`.
`roave/security-advisories` is a hard dep on master/dev-latest in
`composer-ci.json` — composer will refuse to install vulnerable transitive
deps.

When the audit reports a CVE, prefer bumping the affected package directly
(`composer update vendor/pkg --with-all-dependencies`) over loosening
constraints.

## Local install notes

The local environment may have a different `ext-mongodb` version from
`composer-ci.lock`'s pinned `mongodb/mongodb`. If `composer update` fails
on platform req, append `--ignore-platform-req=ext-mongodb`. CI doesn't
need this — the runner installs the right extension via setup-php.
