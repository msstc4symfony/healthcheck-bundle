# CI

`.github/workflows/checks.yml` delegates everything to the shared workflow:

```yaml
jobs:
  standard:
    uses: msstc4symfony/bundle-standard/.github/workflows/php-bundle.yml@v1.0.0
    with:
      slug: msstc4symfony/healthcheck-bundle
      extensions: 'mbstring, xml, ctype, iconv, intl, redis, mongodb, memcache, memcached, rdkafka'
      infection-min-msi: 72
      infection-min-covered-msi: 72
```

- Jobs: standard-check (verifier), PHP lint, PHPStan (`phpstan-ci.neon`),
  PHP-CS-Fixer, Rector, deptrac, composer validate + audit, PHPUnit on
  PHP {8.4, 8.5} × Symfony {7.4, 8.x} plus a `--prefer-lowest` cell
  (PHP 8.4, Symfony 7.4), PHPUnit without optional libraries, Roave BC check
  (blocking, against the latest stable tag), Infection (push to `main`, blocking below 72 % MSI / covered MSI). Inputs and behaviour: `bundle-standard/README.md`.
- `extensions` covers every checker's client extension; with fewer, the
  Kafka / Memcache / Memcached checker tests are skipped, not run.
- The standard is pinned to an exact tag; upgrading is a deliberate edit.
- Infection thresholds: measured MSI 79.9 % (2026-10-02 UTC)
  minus a margin. Raise them when the score stays higher.
- Prefer-lowest reproduction: copy the repo to `$TMPDIR`, drop roave + deptrac,
  pin every listed `symfony/*` to `7.4.*`, `COMPOSER=composer-ci.json composer
  update --prefer-lowest --prefer-stable`, run `vendor/bin/phpunit` (the CI step
  list is in `bundle-standard`'s `php-bundle.yml`).
- Codecov is off (`run-codecov` defaults to `false`): no `CODECOV_TOKEN` yet.
- Change the gate in `bundle-standard`, never by forking the workflow here.
