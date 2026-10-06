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
are three implementations chained by `HealthCheckBundle::loadExtension()` based on bundle config:

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

`HealthCheckerCompletedEvent::$checkerClass` and `ParallelAction`'s
`parallel: X failed (…)` name the checker behind the bundle's decorators:
`@internal CheckerClass::of()` follows `CheckerDecoratorInterface::decoratedCheckerClass()`,
implemented by the Timeout / Deferred / NonCritical decorators. The interface is
public API: an application's own decorator implements it so reports name the
real checker class.

`HealthController` answers in text unless the format resolves to `json`:
request/route format → `?_format=` → `Accept` header. Text output sections:
`Result`, `Errors`, `Messages`, `Warnings`; each title is on its own line and
every line of a section starts with a tab, an empty section reads
`Errors: none`. The console commands print the same layout but omit empty
sections and show `Messages` only with `-v` (or when there are errors).

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
   `CheckInterface::class` tag. Drops a checker whose detector implements
   `WrappedTargetDetectorInterface` (public API; built-in: only `LockStoreDetector`:
   a `StoreFactory::createStore` store around a connection id) when the
   wrapped service is probed by a checker not listed in `non_critical`, and
   the dropped checker's id is in neither `non_critical` nor
   `timeouts.overrides` (the pass reads both parameters). Finally it rejects
   ids in either option that no `CheckInterface`-tagged service has, with
   `InvalidConfigurationException` listing the known ids.
2. **`HealthCheckerTimeoutDecorationPass`** — wraps each
   `CheckInterface`-tagged service in `TimeoutCheckerDecorator`, retags the
   wrapper.
3. **`HealthCheckerDeferredConstructionPass`** (`@internal`) — wraps every
   readiness-only checker (`AbstractReadinessChecker` subclasses; looks
   through the timeout decorator and through a `ChildDefinition`'s parent
   chain) in
   `DeferredReadinessCheckerDecorator`, which gets the rest of the chain as a
   `ServiceClosureArgument` and builds it only inside a readiness probe. A
   target whose constructor throws becomes `<label> failed (<message>)`; the
   label is the checker's own `label()`, read at compile time from a PHP 8.4
   lazy ghost holding only the scalar constructor args (fallback
   `<CheckerClass> (<service>)`). Messages go through `CredentialRedactor`
   on readiness and is never built for liveliness. The pass also hands over the
   checker class (third argument) so events can name it without building it.
4. **`HealthCheckerCriticalityDecorationPass`** — wraps in
   `NonCriticalCheckerDecorator` for checkers listed in
   `non_critical` config, retags the wrapper. The decorator turns both the
   inner's errors and an exception thrown by its `check()` into warnings.

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

`Resources/config/services.php` PSR-4-loads everything under the bundle
namespace except the top-level `DependencyInjection/*.php` (compiler passes,
helpers), the concrete classes in `Application/Health/Check/Checker/` (built as
`Definition`s by detectors or registered by the application), `Resources/` and
the bundle class. Detectors under `DependencyInjection/Detector/` are loaded so
they receive the `healthcheck.detector` tag via autoconfigure. The interfaces
must stay in the scan — see `known-issues.md` "`services.php` must keep the
bundle's interfaces in the scan".

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
- Yielded ids are keyed `healthcheck.checker.{service_id}` (lock stores:
  `healthcheck.checker.<resource names joined by +>`, see `known-issues.md`).
  The pass tags the yielded `Definition` with `CheckInterface::class` and
  marks it autowired.
- Detectors may implement subclass detection
  (`$class === Target::class || is_subclass_of($class, Target::class)`); this
  is the canonical pattern for class-based detectors.

`CachePoolDetector` skips pools that `CachePoolLocality` (`@internal`,
`DependencyInjection/`) classifies as local (in-memory / local disk / FrameworkBundle
system cache, chains of only such adapters) — see
`known-issues.md` "Этап B: события, кеш-пулы, вывод".

The 17 built-in detectors are listed in `tests/Unit/DependencyInjection/HealthCheckerAutoDetectionPassTest`
and have per-detector unit tests under `tests/Unit/DependencyInjection/Detector/`.

Adding a third-party detector: register a service implementing
`CheckerDetectorInterface`. Autoconfigure tags it
`healthcheck.detector` automatically. No subclass of `HealthCheckBundle`
needed.

`WrappedTargetDetectorInterface` (same directory) is public API too: a
third-party detector implements it when its checker probes a client wrapping
another service. `wrappedTarget($container, $checker)` returns the wrapped
service id or `null`; the pass resolves aliases and skips the checker under
the rule in "Compiler pass chain" step 1.

## AbstractReadinessChecker template

Most infrastructure checkers extend `AbstractReadinessChecker`:

```php
abstract readonly class AbstractReadinessChecker implements CheckInterface
{
    final public function isSupport(Context $context): bool;
    final public function check(CheckResult $result, Context $context): CheckResult;

    abstract protected function doCheck(): ?string;
    abstract protected function label(): string;
    protected function skipReason(): ?string { return null; }
}
```

Hook semantics:
- `doCheck()` — probe the underlying service; throw any `Throwable` to mark
  as failed. A returned string is appended (credentials masked) as `<label> passed (<detail>)`
  (`ElasticaConnectionChecker`: `cluster status: green`; status `red` throws,
  so it reads `failed (cluster status: red)`); `null` gives `<label> passed`.
- `label()` — human-readable identifier shown in HTTP / CLI output. **Do not
  leak Symfony-internal class names** (see `known-issues.md`).
- `skipReason()` — return a short string to emit
  `<label> skipped (<reason>)` instead of probing. Used when the underlying
  driver is unavailable in the current runtime (e.g., APCu in CLI without
  `apc.enable_cli`, `NullAdapter`, Messenger transport without
  `MessageCountAwareInterface`). A `Throwable` thrown here fails the check
  like one from `doCheck()`.

`CacheChecker`, `MessengerTransportChecker` and `ElasticaConnectionChecker`
use the `skipReason()` hook.
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

`HealthCheckBundle` is an `AbstractBundle`: `configure()` holds the
`msstc4symfony_healthcheck` tree, `loadExtension()` imports `services.php`,
sets the parameters and wires the action pipeline. Ids live as constants on
`DependencyInjection\ContainerIds` (`ALIAS`, `PARAM_*`, `SERVICE_*`) so the
compiler passes can use them without depending on the bundle class (DEPTRAC).
Container parameters:
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

## Не-final классы

- `Application/Health/Check/Checker/AbstractReadinessChecker` — template
  method base for readiness checkers (bundle and application ones); its
  `isSupport()` / `check()` are `final`.
- `Presentation/Command/AbstractHealthCommand` — template base of the
  liveliness / readiness console commands, which only supply `getType()`.

Every other concrete class is `final` (`final readonly` where stateless).
