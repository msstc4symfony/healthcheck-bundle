# Changelog

## 1.1.0

First release under `msstc4symfony/healthcheck-bundle` / `Msstc4Symfony\HealthCheckBundle`
(previously `MaxShamaev\HealthCheckBundle`).

### Changed

- The configuration root is `msstc4symfony_healthcheck` (was `maxshamaev_healthcheck`):
  rename `config/packages/maxshamaev_healthcheck.yaml` and its root key.
- `symfony/yaml` is now required (the extension loads `services.yaml`; it used to
  arrive only transitively).
- CI runs the shared `bundle-standard` gate on PHP 8.4/8.5 × Symfony 6.4/7.4/8.x with
  every checker's client extension installed.

### Fixed

- Test suite compatibility with Symfony 8 (`KernelTestCase::runCommand()` became static).
