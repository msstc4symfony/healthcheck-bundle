# Changelog

## 1.1.1

### Fixed

- Container compilation crashed (`ClassNotFoundError`) in applications containing a service
  whose class extends a class from a package that is not installed — e.g. Symfony 8.1 with
  security-bundle but without symfony/validator (`UserPasswordValidator`). Detectors now check
  service classes through container reflection; classes given as `%parameters%` are detected too.

### Changed

- Tests follow the shared `bundle-standard` 1.7 layout and configuration; CI also runs the suite
  without optional libraries.

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

- Container compilation failed on recent FrameworkBundle releases (6.4.x latest, 7.4, 8.1),
  which register abstract templates such as `lock.store.combined.abstract`; auto-detected
  checkers now skip abstract services. Explicitly configured HTTP targets still fail loudly
  on a bad client reference.
- Test suite compatibility with Symfony 8 (`KernelTestCase::runCommand()` became static).
