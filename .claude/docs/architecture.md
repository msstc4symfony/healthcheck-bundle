# Architecture

## Layers

The codebase splits into three layers under `src/`, enforced by DEPTRAC
(`deptrac.yaml`). Cross-layer rules:

| Layer | Allowed deps |
|-------|--------------|
| `Application/` | None (pure domain core) |
| `Presentation/` | `Application` |
| `DependencyInjection/` | `Application` |
| `HealthCheckBundle` (root) | `Application`, `DependencyInjection` |

`Application` may use Symfony **contracts** (e.g. `Psr\EventDispatcher`,
`Symfony\Contracts\HttpClient\HttpClientInterface`, cache/lock interfaces from
`symfony/cache` / `symfony/lock`) but must never reach into `Presentation` or
`DependencyInjection`.

## Execution path

Single public entry point: `ActionInterface::run(Request) -> Response`. There
are three implementations chained by the DI extension based on bundle config:

```
                     ┌─ Action (sequential, default)
ActionInterface ◀── ─┤
                     └─ ParallelAction (fiber-based, opt-in via config.execution: parallel)

  (optionally wrapped by CachedActionDecorator if config.cache.enabled = true)
```

Per-checker step:
1. `Action` receives `iterable<CheckInterface>` via
   `#[AutowireIterator(CheckInterface::class)]`.
2. Each checker's `isSupport(Context)` filters by `CheckTypeEnum`
   (`READINESS` / `LIVELINESS`).
3. Selected checkers mutate a shared `CheckResult` (`messages[]` / `errors[]`
   / `warnings[]`) via `check()`.
4. Final `Response` has `success = (errors === [])`.

`Action` and `ParallelAction` compose `SafeEventDispatcher`
(`Application/Health/Check/Event/`), which wraps the optional PSR-14
dispatcher and swallows listener exceptions — a buggy listener must never
fail a probe.

## Compiler pass chain

`HealthCheckBundle::build()` registers four passes in this order:

1. **`HealthCheckerAutoDetectionPass`** — discovers
   `CheckerDetectorInterface` services (tagged `healthcheck.detector` via
   `#[AutoconfigureTag]`), instantiates each via `new $class()`, calls
   `detect($container)`, registers yielded checker `Definition`s with the
   `CheckInterface::class` tag.
2. **`HealthCheckerTimeoutDecorationPass`** — wraps each
   `CheckInterface`-tagged service in `TimeoutCheckerDecorator`, retags the
   wrapper.
3. **`HealthCheckerDeferredConstructionPass`** (`@internal`) — wraps every
   readiness-only checker (`AbstractReadinessChecker` subclasses,
   `ElasticaConnectionChecker`; looks through the timeout decorator) in
   `DeferredReadinessCheckerDecorator`, which gets the rest of the chain as a
   `ServiceClosureArgument` and builds it only inside a readiness probe. A
   target whose constructor throws becomes `<checker id> failed (<message>)`
   on readiness and is never built for liveliness.
4. **`HealthCheckerCriticalityDecorationPass`** — wraps in
   `NonCriticalCheckerDecorator` for checkers listed in
   `non_critical` config, retags the wrapper.

Order matters: timeout is the **innermost** decorator. Final composition is
`NonCritical(Deferred(Timeout(inner)))` (Deferred only for readiness-only
checkers). Deferred sits outside Timeout so the timeout message still names
the real checker class, and inside NonCritical so a construction failure of a
non-critical checker stays a warning. A non-critical checker that exceeds its budget
yields a warning (not an error) — without this order it would be flipped.

## Checker registration

Two paths into the iterator that `Action` consumes:

1. **User-supplied checkers** — any class implementing `CheckInterface` in an
   autoconfigured PSR-4 path is automatically tagged via
   `#[AutoconfigureTag(CheckInterface::class)]` on the interface.
2. **Auto-detected infrastructure checkers** — see "Detector contract" below.

`services.yaml` PSR-4-loads everything under the bundle namespace
**except** `DependencyInjection/` (loaded by Symfony itself) and
`Application/Health/Check/Checker/` (their classes are not auto-registered as
generic services; they are constructed as `Definition`s by detectors). A
second PSR-4 block explicitly registers `DependencyInjection/Detector/` so
detector services receive the `healthcheck.detector` tag via autoconfigure.

## Detector contract

`CheckerDetectorInterface` (`src/DependencyInjection/Detector/`) exposes:

```php
public function detect(ContainerBuilder $container): iterable<string, Definition>;
```

Conventions:
- The detector class **must** be stateless with a zero-argument constructor.
  `HealthCheckerAutoDetectionPass` instantiates it via `new $class()`.
  Stateful dependencies must be resolved lazily inside `detect()` from the
  passed `ContainerBuilder`.
- Yielded ids are keyed `healthcheck.checker.{service_id}`. The pass tags the
  yielded `Definition` with `CheckInterface::class` and marks it autowired.
- Detectors may implement subclass detection
  (`$class === Target::class || is_subclass_of($class, Target::class)`); this
  is the canonical pattern for class-based detectors.

The 17 built-in detectors are listed in `tests/Unit/DependencyInjection/HealthCheckerAutoDetectionPassTest`
and have per-detector unit tests under `tests/Unit/DependencyInjection/Detector/`.

Adding a third-party detector: register a service implementing
`CheckerDetectorInterface`. Autoconfigure tags it
`healthcheck.detector` automatically. No subclass of `HealthCheckBundle`
needed.

## AbstractReadinessChecker template

Most infrastructure checkers extend `AbstractReadinessChecker`:

```php
abstract readonly class AbstractReadinessChecker implements CheckInterface
{
    final public function isSupport(Context $context): bool;
    final public function check(CheckResult $result, Context $context): CheckResult;

    abstract protected function doCheck(): void;
    abstract protected function label(): string;
    protected function skipReason(): ?string { return null; }
}
```

Hook semantics:
- `doCheck()` — probe the underlying service; throw any `Throwable` to mark
  as failed.
- `label()` — human-readable identifier shown in HTTP / CLI output. **Do not
  leak Symfony-internal class names** (see `known-issues.md`).
- `skipReason()` — return a short string to emit
  `<label> skipped (<reason>)` instead of probing. Used when the underlying
  driver is unavailable in the current runtime (e.g., APCu in CLI without
  `apc.enable_cli`, `NullAdapter`, Messenger transport without
  `MessageCountAwareInterface`).

`CacheChecker` and `MessengerTransportChecker` use the `skipReason()` hook.
Direct `implements CheckInterface` is still allowed when the template doesn't
fit, but it's the exception, not the norm.

## DTOs

- `Application/Health/Check/DTO/Request` — input; carries `type` +
  `options`.
- `Application/Health/Check/DTO/Context` — derived from Request for checker
  consumption.
- `Application/Health/Check/DTO/CheckResult` — mutable accumulator
  (`messages[]` / `errors[]` / `warnings[]`).
- `Application/Health/Check/DTO/Response` — output; `success` is a property
  hook computing `errors === []`.
- `Application/Health/Check/DTO/HttpProbeTarget` — VO that bundles
  url + method + expected status codes + timeout for `HttpClientChecker`.

All DTOs use PHP 8.4 readonly + asymmetric visibility / property hooks where
they reduce boilerplate.

## Configuration surface

`HealthCheckExtension` exposes these container parameters:
- `msstc4symfony_healthcheck.http_client_targets`
- `msstc4symfony_healthcheck.default_timeout_ms`
- `msstc4symfony_healthcheck.timeout_overrides`
- `msstc4symfony_healthcheck.non_critical_checkers`

Plus two service ids:
- `msstc4symfony_healthcheck.action.parallel`
- `msstc4symfony_healthcheck.action.cached`

The `ActionInterface` alias points at one of `Action::class`,
`msstc4symfony_healthcheck.action.parallel`, or
`msstc4symfony_healthcheck.action.cached` depending on config.
