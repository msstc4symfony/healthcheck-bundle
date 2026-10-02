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

## Lock store check names contained the container hash (fixed in v1.3.0)

FrameworkBundle registers lock stores as hidden `.lock.<resource>.store.<hash>`
services (hash of the DSN). Since v1.3.0 `LockStoreDetector` labels them by the
resources using them, read from the `lock.<resource>.factory` child definitions
(`lock.factory.abstract`, argument 0): `Lock store (lock.default)`;
`lock.<resource>[<i>]` for the i-th store of a combined store
(`lock.store.combined.abstract`, argument 0 = list of references);
`lock.a, lock.b` for a store shared by resources (8.1's `.lock.flock.store`).
A store no factory references keeps its id as label. The **checker id is
unchanged** (`healthcheck.checker..lock.<resource>.store.<hash>`): it is the key
of `non_critical` / `timeouts`, so renaming it would silently drop existing
configuration (BC).

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

## Probe output format negotiation (since v1.2.0)

`HealthController::responseFormat()`: `Request::getRequestFormat(null)` (explicit
`setRequestFormat()` or a route `_format` attribute — the bundle's routes declare none) →
`_format` query parameter (read via `query->all()`: `InputBag::get()` throws a 400 on
`?_format[]=…`) → `Request::getPreferredFormat(null)` (Accept header). Only `json` switches
output; anything else (incl. `*/*`, `text/html` — Kubernetes sends `*/*`) stays text. Before
1.2.0 only the route attribute counted, so `?_format=json` and `Accept` were ignored.

## `CacheChecker` skip semantics changed in v1.1.0

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
- **PHP version matrix.** Currently single 8.4. Add 8.5+ when those land.

## Детекторы роняли компиляцию на классах с неустановленным родителем (2026-10-01 UTC)

Детекторы проверяли класс **каждого** сервиса приложения через `is_subclass_of()`, который
автозагружает класс. В реальном приложении (skeleton Symfony 8.1 + security-bundle без
symfony/validator) это `UserPasswordValidator` из security-core, чей родитель
`ConstraintValidator` не установлен → `cache:clear` падал с `ClassNotFoundError`. В тестовом ядре
таких сервисов нет — нашлось только установкой в чистый skeleton. Все проверки теперь через
`DependencyInjection\ServiceClass::is()` (`ContainerBuilder::getReflectionClass($class, false)`):
переживает отсутствующего родителя и разрешает `%param%`-классы. Если искомого типа нет
(например, нет ext-rdkafka), совпадает только точное имя класса — как раньше.

## Liveliness падал из-за readiness-зависимостей (исправлено в v1.1.2, 2026-10-01 UTC)

Реальное приложение (Symfony 8.1, PHP 8.4 без ext-sysvsem, `framework.lock: '%env(LOCK_DSN)%'`
с redis): `GET /_/healthcheck/liveliness` → 500 `Semaphore extension (sysvsem) is required.` из
`SemaphoreStore::__construct`. Причина: `Action`/`ParallelAction` получают чекеры через
`#[AutowireIterator]`, и **каждый** чекер (вместе с целевым клиентом) конструируется при обходе
итератора — до фильтра `isSupport()` по типу проверки. Исключение в конструкторе любой
readiness-зависимости роняло весь прогон, включая liveliness (в Kubernetes — рестарты по кругу).

Исправление: `HealthCheckerDeferredConstructionPass` оборачивает readiness-only чекеры в
`DeferredReadinessCheckerDecorator` (цепочка передаётся как `ServiceClosureArgument`). Для
liveliness клиент вообще не создаётся; на readiness ошибка конструктора становится
`<label> failed (<сообщение>)` (с v1.1.3 — собственный label чекера; в v1.1.2 был id сервиса). Публичные конструкторы чекеров не менялись (BC).
Побочный эффект: `HealthCheckerCompletedEvent::$checkerClass` для readiness-only чекеров теперь
`DeferredReadinessCheckerDecorator` (раньше `TimeoutCheckerDecorator`). Чекер, который
поддерживает liveliness (прямой `CheckInterface` без шаблона), по-прежнему конструируется
сразу — его зависимость обязана быть конструируемой.

Регрессия: `tests/Integration/Functional/ReadinessDependencyIsolationTest` (фикстуры
`UnconstructibleDependencyFixture` / `DependentReadinessCheckerFixture`; `TestKernel` принимает
замыкание с доп. сервисами — каждому варианту своё окружение). Фикстура-зависимость должна
реально использоваться в `doCheck()`: иначе Rector (`make fix`) удаляет «неиспользуемое»
свойство вместе с конструктором и тест молча зеленеет без исправления.

## LockStoreDetector проверял неиспользуемые хранилища FrameworkBundle (исправлено в v1.1.2, 2026-10-01 UTC)

FrameworkBundle 8.1 (`Resources/config/lock.php`) всегда регистрирует `.lock.flock.store`
(`FlockStore`) и `.lock.semaphore.store` (`SemaphoreStore`), даже если ресурс их не использует.
Детектор брал любой `PersistingStoreInterface` и строил для них чекеры — отсюда
`SemaphoreStore` без sysvsem. `FrameworkExtension::registerLockConfiguration` (6.4–8.1) вешает тег
`lock.store` на каждое хранилище настроенного ресурса: DSN-хранилища —
`.lock.<resource>.store.<hash>` (`Definition(PersistingStoreInterface)` + `StoreFactory::createStore`),
в 8.1 `flock`/`semaphore` — тегом на предопределённый `.lock.flock.store`/`.lock.semaphore.store`.
`CombinedStore` (`ChildDefinition` от `lock.store.combined.abstract`) тега не имеет, но его
составные хранилища тегированы. Правило детектора: скрытые (`.`-префикс) сервисы — только с
тегом `lock.store`; хранилища, зарегистрированные приложением под обычным id, проверяются
как раньше. Если приложение передаёт в `framework.lock` id сервиса-соединения (`\Redis`, DBAL connection, …),
FrameworkBundle строит вокруг него `.lock.<resource>.store.<hash>` с фабрикой
`StoreFactory::createStore(@сервис)`. До v1.3.0 то же соединение проверялось дважды (свой чекер +
lock store). С v1.3.0 `HealthCheckerAutoDetectionPass` собирает цели всех обнаруженных чекеров
(аргумент 0, алиасы разрешены) и не регистрирует чекер, чья цель — `StoreFactory::createStore`
вокруг уже проверяемого сервиса. Сервис-хранилище (`RedisStore` и т.п.) передать по id нельзя —
`StoreFactory` принимает только соединения/DSN. Сравнивать фабрику нужно по частям массива:
Rector превращает литерал `[StoreFactory::class, 'createStore']` в `StoreFactory::createStore(...)`
(first-class callable), и сравнение с массивом становится всегда ложным.

## Ревью v1.1.2 → v1.1.3 (2026-10-02 UTC)

- **Пароли в выводе проб.** `StoreFactory` и другие фабрики кладут полный DSN в текст исключения,
  а `AbstractReadinessChecker`/`DeferredReadinessCheckerDecorator`/`ElasticaConnectionChecker`
  отдавали его как есть. Теперь всё идёт через `Application\Health\Check\CredentialRedactor`
  (user info в URL и секретные query-параметры); с v1.2.0 — и `ParallelAction`
  (`parallel: X failed (...)`).
- **Label при ошибке конструирования** вычисляется в `HealthCheckerDeferredConstructionPass` через
  `ReflectionClass::newLazyGhost()`: в «призрак» кладутся только скалярные promoted-аргументы
  конструктора (с разрешёнными `%параметрами%`), затем вызывается `label()`. Если `label()` трогает
  что-то ещё — инициализатор бросает, берётся fallback `<КороткийКлассЧекера> (<сервис>)`.
- **`ElasticaConnectionChecker` оставлен вне шаблона** (CR-007): его вывод содержит статус кластера
  в «passed»-сообщении, а readonly-шаблон с `doCheck(): void` не может передать деталь пробы без
  смены сигнатуры абстрактного метода (BC-слом для сторонних наследников). Поэтому в pass остаётся
  список readiness-only типов `[AbstractReadinessChecker, ElasticaConnectionChecker]`.
- `HealthCheckerCompletedEvent::$checkerClass` был классом внешнего декоратора (CR-008) — исправлено
  в v1.2.0, см. раздел ниже.
- Пользовательский чекер, объявленный как `ChildDefinition` (без класса до `ResolveChildDefinitionsPass`), не распознавался как readiness-only и создавался сразу (eager). С v1.3.0 `HealthCheckerDeferredConstructionPass::flatten()` склеивает цепочку родителей (класс — первый непустой от ребёнка; аргументы родителя, поверх — `index_N` ребёнка) только для классификации и label; сами определения не меняются.
- В текстовом выводе HTTP-проб `warnings` не печатались — с v1.2.0 есть секция `Warnings:` (в конце,
  после `Messages:`, чтобы не сдвигать строки для существующих парсеров); консольные команды печатают
  `Warnings:` только если они есть.
- RED для `LockStoreSelectionTest` воспроизводится только на FrameworkBundle ≥ 8.1 (CI-lock сейчас
  8.0.x — там предопределённых хранилищ нет): копия в `$TMPDIR` с минимальным профилем +
  `composer require --dev symfony/lock:^8.1`.

## Этап B → v1.2.0 (2026-10-02 UTC)

- **Реальный класс чекера в событиях.** Декораторы бандла (`TimeoutCheckerDecorator`,
  `NonCriticalCheckerDecorator`, `DeferredReadinessCheckerDecorator`) реализуют `@internal`
  `Checker\CheckerDecoratorInterface::decoratedCheckerClass()`; `Checker\CheckerClass::of()` разворачивает
  цепочку. `Deferred` не может спросить ещё не построенный чекер, поэтому класс передаёт
  `HealthCheckerDeferredConstructionPass` третьим аргументом (класс внутреннего определения за
  Timeout, с разрешёнными `%параметрами%`). Пользовательские декораторы интерфейс не реализуют —
  для них событие по-прежнему называет сам декоратор.
- **Кеш-пулы.** Классификация вынесена в `@internal DependencyInjection\CachePoolLocality`
  (не в `Detector/` — тот каталог грузится как сервисы). Идёт по цепочке `ChildDefinition` (как `CachePoolPass`),
  класс и фабрика берутся первые непустые от ребёнка к родителю (как в DI: фабрика ребёнка
  перекрывает `createSystemCache` родителя):
  локальные адаптеры (Array, Apcu, Filesystem, FilesystemTagAware, PhpFiles, PhpArray, Null, включая
  подклассы) и `cache.adapter.system` — он объявлен как `AdapterInterface` с фабрикой
  `AbstractAdapter::createSystemCache`, класс ничего не говорит, поэтому признак — имя метода
  фабрики — пропускаются. `ChainAdapter` локален, только если локальны все звенья; аргумент 0 до
  `CachePoolPass` — id сервисов, после — inline `ChildDefinition` (наш pass идёт после, priority 0 <
  32). Неизвестные/сторонние адаптеры, `ProxyAdapter`, `Psr16Adapter`, `TagAwareAdapter`,
  помеченный `cache.pool` вручную, проверяются как раньше (лучше лишняя проба, чем пропущенная
  зависимость). Несколько пулов на одном Redis (`cache.app` + наследники `cache.rate_limiter`,
  `cache.scheduler`, …) дают по пробе на пул — дедупликации по провайдеру нет.
- **Неровный отступ текстового вывода** (`Messages: \nfirst\n\tsecond`) — старое
  `implode(PHP_EOL . "\t", …)`, сохранён ради существующих парсеров; секция `Warnings` идёт тем же
  форматом. Выравнивать — только в 2.0. Ответы проб несут `Vary: Accept` (формат зависит от Accept).
- **Non-critical + исключение = error** (до v1.3.0). `NonCriticalCheckerDecorator` понижал только
  добавленные ошибки; исключение из `check()` в последовательном `Action` пролетало наружу, в
  `ParallelAction` становилось `parallel: X failed (…)` в `errors`. С v1.3.0 декоратор ловит
  `Throwable`, отбрасывает частичный вывод и пишет warning `<класс чекера> failed (<сообщение,
  CredentialRedactor>)`. Критичный чекер по-прежнему бросает наружу в `Action`.
- README до 1.2.0 советовал тег `healthcheck.checker` и `$result->messages[] = …` для своего
  чекера — оба не работают (тег — FQCN `CheckInterface`, свойства `private(set)`); исправлено.
  Ручная регистрация `CacheChecker` (README «Probing a Local Cache Pool») закреплена тестом
  `CachePoolSelectionTest::testLocalPoolCanBeProbedByRegisteringCacheCheckerExplicitly`.
- RED для `CachePoolSelectionTest::testAppPoolOnRedis…` использует `redis://127.0.0.1:1`
  (мгновенный отказ соединения) — Redis-сервер не нужен, но нужен ext-redis или predis (иначе skip).


## v1.3.0: bundle-standard 1.8.0, prefer-lowest, level 10 (2026-10-02 UTC)

- **Prefer-lowest (PHP 8.4, Symfony 6.4.0) проходит.** Что потребовалось:
  - `phpunit/phpunit: >=11.5` в обоих манифестах: 10.5 не знает
    `<source ignoreIndirectDeprecations>` шаблона. Фактический минимум диктует
    `roave/security-advisories` (сейчас 11.5.50).
  - `conflict: symfony/error-handler <6.4.10|>=7.0,<7.0.10|>=7.1,<7.1.3` в обоих манифестах:
    до этих версий `ErrorHandler` трогает `E_STRICT` (deprecated в PHP 8.4). В `require` поднять
    патч нельзя — верификатор требует ровно `^6.4|^7.0|^8.0`; `conflict` он не проверяет (кроме
    `symfony/symfony`).
  - **Реальный баг DBAL 3**: `DBALConnectionChecker` звал `Connection::getServerVersion()`, а в
    DBAL 3.x этот метод `private` → на отключённом соединении readiness всегда падала с
    «Call to private method». Теперь `executeQuery(getDatabasePlatform()->getDummySelectSQL())`
    (есть в 3.x и 4.x); тест на реальном `pdo_sqlite` ловит регрессию на обеих версиях. Ограничение
    DBAL (`^3.0|^4.0`) не менялось.
  - FrameworkBundle < 6.4.13 в `boot()` всегда регистрирует `ErrorHandler` (с 6.4.13 — нет, если
    есть `symfony/runtime`); PHPUnit 11+ помечает такие тесты risky («did not remove its own
    exception handlers»). Это не баг бандла — `TestKernel` снимает обработчики, которые положил
    его `boot()`, в `shutdown()` (сравнение вершины стека до/после через `set_*_handler(null)` +
    `restore_*_handler()`).
- **PHPStan level 10**: две ошибки в `CachePoolDetector` (`mixed` из атрибутов тега) — имя пула
  теперь берётся `poolName()` с сужением через `is_array`/`is_string`, без `@var`.
- `symfony/*-contracts`: в `require` объявлять нечего — из контрактов код использует только
  `HttpClient`, а он нужен лишь опциональному `HttpClientChecker` (приходит с `symfony/http-client`
  из `composer-ci.json`).

## Ревью v1.2.0 → v1.3.0, фиксы в v1.3.1 (2026-10-02 UTC)

- **M1 — пропуск lock store поверх проверяемого соединения.** `LockStoreChecker` делает
  `save()`/`delete()` (EVAL на Redis, INSERT в `lock_keys` на DBAL), т.е. проверяет больше, чем
  PING/`SELECT 1` соединения. С 1.3.1 store пропускается, только если цель проверяет хотя бы один
  чекер не из `non_critical`, и id чекера store не упомянут ни в `non_critical`, ни в
  `timeouts.overrides` (`HealthCheckerAutoDetectionPass` читает оба параметра расширения сам).
  Решение «переносить критичность» вместо «не пропускать вообще»: дубль на одном и том же Redis
  был причиной фичи 1.3.0.
- **m4.** Знание о форме `framework.lock` ушло в `LockStoreDetector::wrappedTarget()` через
  `@internal WrappedTargetDetectorInterface`; pass больше не импортирует `StoreFactory`.
  Интерфейс `@internal` — не обещаем сторонним детекторам API в патч-релизе.
- **m1/m2 `flatten()`.** Цикл `parent` (pass стоит до `ResolveChildDefinitionsPass`) раньше
  съедал память — теперь определение возвращается как есть, ошибку даёт Symfony. Именованные
  аргументы (`$name`) сводятся к позиции через reflection конструктора итогового класса, иначе
  позиционный аргумент родителя побеждал именованный у потомка.
- **s1.** `DBALConnectionChecker` всегда шлёт dummy SELECT: `isConnected()` остаётся `true`, когда
  сервер закрыл долгоживущее соединение (worker mode).
- **Отклонено s2** (поднять `symfony/framework-bundle` до `^6.4.13` вместо `conflict` на
  `symfony/error-handler`): верификатор `bundle-standard` требует в `require` ровно
  `^6.4|^7.0|^8.0`; к тому же `conflict` уже, чем такое ограничение (бьёт только по сломанным
  релизам error-handler), а «потребитель молча остаётся на 1.2.x» верно для обоих вариантов.
- **Отклонено s3** (label вместо FQCN в `NonCriticalCheckerDecorator`): у `CheckInterface` нет
  публичного `label()` (он `protected` в `AbstractReadinessChecker`, а такие чекеры исключения
  не бросают); новый публичный контракт — материал минорного релиза. FQCN совпадает с форматом
  `parallel: X failed (…)` и сообщений `TimeoutCheckerDecorator`.
- **s5** уже покрыт: `HealthCheckerAutoDetectionPassTest::testProcessPreservesChildCachePoolNameOverParent`.
- **m5.** Порядок элементов класса `TestKernel` выправлен вручную; правило `ordered_class_elements`
  в `.php-cs-fixer.dist.php` включить нельзя — файл `ExactFileRule` в `bundle-standard` (follow-up).
