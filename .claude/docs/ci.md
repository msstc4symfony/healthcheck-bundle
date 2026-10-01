# CI

`.github/workflows/checks.yml` delegates everything to the shared workflow:

```yaml
jobs:
  standard:
    uses: msstc4symfony/bundle-standard/.github/workflows/php-bundle.yml@v1.6.0
    with:
      slug: msstc4symfony/healthcheck-bundle
      extensions: 'mbstring, xml, ctype, iconv, intl, redis, mongodb, memcache, memcached, rdkafka'
```

- Jobs: standard-check (verifier), PHP lint, PHPStan (`phpstan-ci.neon`),
  PHP-CS-Fixer, Rector, deptrac, composer validate + audit, PHPUnit on
  PHP {8.4, 8.5} × Symfony {6.4, 7.4, 8.x}, Roave BC check, Infection (push to
  `main`). Inputs and behaviour: `bundle-standard/README.md`.
- `extensions` covers every checker's client extension; with fewer, the
  Kafka / Memcache / Memcached checker tests are skipped, not run.
- The standard is pinned to an exact tag; upgrading is a deliberate edit.
- Codecov is off (`run-codecov` defaults to `false`): no `CODECOV_TOKEN` yet.
- Change the gate in `bundle-standard`, never by forking the workflow here.
