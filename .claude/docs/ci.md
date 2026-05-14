# CI

Single workflow: `.github/workflows/checks.yml`. Triggers on `push` to
`main` and any PR. `paths-ignore` skips docs-only changes
(`**/*.md`, `LICENSE`). `concurrency` cancels in-progress PR runs on new
pushes.

## Jobs

```
setup ────┬── lint
          ├── phpstan        (matrix-independent; full optional ext list)
          ├── cs-fixer
          ├── rector
          ├── deptrac
          ├── composer-audit (also runs composer validate --strict)
          └── bc-check       (continue-on-error; informational)

phpunit (matrix: Symfony 6.4 / 7.* / 8.*)
infection (push-to-main only; continue-on-error)
```

`phpunit` does **not** depend on `setup` — each matrix variant runs
`composer require --no-update` + `composer update` to pin the Symfony
version, then PHPUnit. The `setup`-cached vendor wouldn't match.

`bc-check` checks out with `fetch-depth: 0` (BC tool needs git tags).

## Caches

| Cache | Key | Used by |
|-------|-----|---------|
| `vendor/` | `vendor-<os>-php<ver>-<hash(composer-ci.lock)>` | setup + 5 quality jobs + infection |
| `/tmp/phpstan` | `phpstan-<hash(composer-ci.lock, phpstan*.neon)>` | phpstan |
| `.php-cs-fixer.cache` | `cs-fixer-<hash(.php-cs-fixer.dist.php, composer-ci.lock)>` | cs-fixer |
| `/tmp/rector` | `rector-<hash(rector.php, composer-ci.lock)>` | rector |
| `vendor/` per Symfony variant | `vendor-<os>-php<ver>-symfony<label>-<hash(composer-ci.json)>` | phpunit matrix |

## Action versions (Node 24)

All JS actions are pinned to Node 24-capable majors:
- `actions/checkout@v5`
- `actions/cache@v5`
- `actions/upload-artifact@v6`
- `codecov/codecov-action@v6`
- `shivammathur/setup-php@v2` (floats to 2.37+, Node 24 since 2.37.0)

When GitHub deprecates a Node version, check each action's `action.yml`
`using:` field via `raw.githubusercontent.com/<repo>/<tag>/action.yml`
before bumping; don't trust release notes alone.

## Codecov upload

Uploaded **only on the Symfony 8 matrix variant** (the `codecov: true`
matrix entry). Three variants would otherwise upload three coverage
reports for the same code, overwriting each other in confusing ways.
Coverage flag is `phpunit`. Both clover and JUnit results upload via
two separate `codecov-action@v6` steps (v5 doesn't accept both in one
call).

## Infection in CI

- Pushes to `main` only. PRs don't run mutation tests to keep feedback
  fast.
- `continue-on-error: true`. First baseline (local): MSI 66.74 % across
  484 mutants. Set min-MSI in `make infection` after the baseline
  stabilises.
- Artifact upload: `var/infection/` with 14-day retention.

## BC check in CI

`bc-check` is informational. It will report ~35 BC breaks against
`v1.0.0` as of current HEAD — accumulated during this refactor cycle.
Promote to required after tagging `v1.1.0` so the comparison resets.

## Mutating composer manifests in CI

The phpunit-matrix job runs `composer require --no-update` on three
Symfony packages (framework-bundle, http-foundation, console) to pin the
matrix version, then `composer update`. This **mutates `composer-ci.json`
in the runner workspace**. That's intentional and harmless — the runner
is ephemeral.
