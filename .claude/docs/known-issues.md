# Known issues / quirks

Items that have bitten us once and might bite again. Living list — append,
don't churn.

## phpunit matrix — now owned by `bundle-standard`

The matrix job lives in the shared reusable workflow (`bundle-standard`
`php-bundle.yml`). Two quirks first found here are handled there for every
bundle:

- `roave/backward-compatibility-check` and `deptrac/deptrac` pin narrow
  `symfony/console` ranges; the matrix job removes both before resolving.
- `browser-kit` / `dom-crawler` must match `http-kernel` (a parent and a child
  from different Symfony majors fatal `WebTestCase` with a signature mismatch). The
  job pins every lockstep `symfony/*` package listed in `composer-ci.json`,
  adds `dom-crawler` whenever `browser-kit` is listed, and fails a cell whose
  resolved `http-kernel` major differs from its label.

Adding a new dev tool with a narrow Symfony range: fix it in
`bundle-standard`, not here.

## Auto-detection skips abstract service templates

Recent FrameworkBundle releases (7.4, 8.1) register abstract
templates such as `lock.store.combined.abstract`. Detectors match by class, so
a checker pointing at one failed container compilation ("reference to an
abstract definition"). `HealthCheckerAutoDetectionPass` now drops any detected
checker whose arguments reference an abstract definition (aliases resolved).
`HttpClientTargetDetector` is exempt: its targets come from explicit config, and a
bad `client` reference there must fail compilation rather than silently drop the probe. The lock file pinned
an older FrameworkBundle (roave/backward-compatibility-check caps
`symfony/console`), so only the CI matrix — which removes roave and resolves
the latest framework — exposed it.

## Lock store checks are named after their `framework.lock` resources

FrameworkBundle registers lock stores as hidden `.lock.<resource>.store.<hash>`
services (hash of the DSN, which changes with the DSN and may carry
credentials). `LockStoreDetector` names each store after the resources using
it, read from the `lock.<resource>.factory` child definitions
(`lock.factory.abstract`, argument 0): `lock.default`; `lock.<resource>[<i>]`
for the i-th store of a combined store (`lock.store.combined.abstract`,
argument 0 = list of references); several names for a store shared by
resources (8.1's `.lock.flock.store`). Names are sorted; the checker id joins
them with `+` (`healthcheck.checker.lock.default+lock.reports`), the label with
`, ` (`Lock store (lock.default, lock.reports)`). A store no factory references
keeps its service id in both. Resource names, not the hashed service id, keep
`non_critical` / `timeouts` keys stable when the DSN changes.

## PHPUnit deprecations from `MemcacheCheckerTest` (pecl memcache)

Stubbing `Memcache` makes PHPUnit generate a class from the extension's
signatures, which use implicitly nullable parameters — deprecated since PHP 8.4.
The 38 deprecations in CI come from the extension, not the bundle; they do not
fail the run. They disappear when pecl memcache ships explicit nullable types.

## Symfony 8 — `KernelTestCase::runCommand()` is now a static method

A private `runCommand()` helper in a `KernelTestCase` subclass fatals on
Symfony 8 ("Cannot make static method ... non static"). The helper in
`HealthCommandFunctionalTest` is `executeCommand()`.

## `services.php` must keep the bundle's interfaces in the scan

`HealthCheckBundle` (an `AbstractBundle`) loads `Resources/config/services.php`;
`symfony/yaml` is not a dependency. `CheckInterface` and
`CheckerDetectorInterface` carry `#[AutoconfigureTag]`, and
`RegisterAutoconfigureAttributesPass` reads that attribute only from classes
that have an (autoconfigured) definition — for interfaces the
`.abstract.<interface>` one the resource loader creates. Excluding a whole
directory (`DependencyInjection/`, `Checker/`) drops those, so detectors lose
the `healthcheck.detector` tag and application checkers lose their tag:
every probe runs with no checkers and still reports `up`. The excludes
therefore name files (`DependencyInjection/*.php`,
`Checker/{*Checker,*Decorator,CheckerClass}.php`).
`HealthCheckBundleTest::testAutoconfigurationTagsDetectorsAndApplicationCheckers`
guards it. Exclude globs resolve relative to `src/Resources/config/`
(`FileLoader::findClasses()`); one that matches nothing silently loads all of
`src/`, and compiler passes and helpers become (unused) services.

## Unknown ids in `non_critical` / `timeouts.overrides` fail the build

`HealthCheckerAutoDetectionPass` throws `InvalidConfigurationException`
listing the known checker ids (every service tagged `CheckInterface` once it has
registered the detected ones). It is the right place: application checkers are
tagged by autoconfiguration before it, and the timeout / criticality passes
that consume the ids are registered right after it in `HealthCheckBundle::build()`,
so no compiler pass can add a checker in between. Without this check a typo
would silently leave the checker critical or on the default budget.

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
   handler install, so no `tests/bootstrap.php` is needed for it.
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

## Probe output format negotiation

`HealthController::responseFormat()`: `Request::getRequestFormat(null)` (explicit
`setRequestFormat()` or a route `_format` attribute — the bundle's routes declare none) →
`_format` query parameter (read via `query->all()`: `InputBag::get()` throws a 400 on
`?_format[]=…`) → `Request::getPreferredFormat(null)` (Accept header). Only `json` switches
output; anything else (incl. `*/*`, `text/html` — Kubernetes sends `*/*`) stays text.

## `CacheChecker` skips `NullAdapter` and APCu in CLI

`CacheChecker` with `NullAdapter` or APCu-in-CLI emits
"<label> skipped (NullAdapter)" / "skipped (APCu in CLI without
apc.enable_cli)" instead of a false "<label> passed". Operational
dashboards that match on the `passed` substring do not count these probes.

## No `version` field in the composer manifests

`composer validate --strict` flags an explicit `version: "1.0.0"` as a
warning when the package is published on Packagist (where versions are
derived from git tags). Neither `composer.json` nor `composer-ci.json`
has it; don't add it when copy-pasting from other references.

## Deferred / on-demand work

Tracked here so it doesn't get forgotten.

- **Per-version PHPStan baselines for the Symfony matrix.** PHPStan
  currently runs once against the locked Symfony version. If `Phpunit`
  matrix surfaces Symfony 7.4 / 8 deprecations, those won't fail
  PHPStan. Tradeoff: matrix-aware PHPStan would multiply CI time × 3.
- **PHP version matrix.** Owned by `bundle-standard` (`php-versions`, PHP 8.4 and 8.5); extend it
  there, not here.

## Детекторы роняли компиляцию на классах с неустановленным родителем (2026-10-01 UTC)

Детекторы проверяли класс **каждого** сервиса приложения через `is_subclass_of()`, который
автозагружает класс. В реальном приложении (skeleton Symfony 8.1 + security-bundle без
symfony/validator) это `UserPasswordValidator` из security-core, чей родитель
`ConstraintValidator` не установлен → `cache:clear` падал с `ClassNotFoundError`. В тестовом ядре
таких сервисов нет — нашлось только установкой в чистый skeleton. Все проверки теперь через
`DependencyInjection\ServiceClass::is()` (`ContainerBuilder::getReflectionClass($class, false)`):
переживает отсутствующего родителя и разрешает `%param%`-классы. Если искомого типа нет
(например, нет ext-rdkafka), совпадает только точное имя класса — как раньше.

## Liveliness падал из-за readiness-зависимостей (2026-10-01 UTC)

Реальное приложение (Symfony 8.1, PHP 8.4 без ext-sysvsem, `framework.lock: '%env(LOCK_DSN)%'`
с redis): `GET /_/healthcheck/liveliness` → 500 `Semaphore extension (sysvsem) is required.` из
`SemaphoreStore::__construct`. Причина: `Action`/`ParallelAction` получают чекеры через
`#[AutowireIterator]`, и **каждый** чекер (вместе с целевым клиентом) конструируется при обходе
итератора — до фильтра `isSupport()` по типу проверки. Исключение в конструкторе любой
readiness-зависимости роняло весь прогон, включая liveliness (в Kubernetes — рестарты по кругу).

Исправление: `HealthCheckerDeferredConstructionPass` оборачивает readiness-only чекеры в
`DeferredReadinessCheckerDecorator` (цепочка передаётся как `ServiceClosureArgument`). Для
liveliness клиент вообще не создаётся; на readiness ошибка конструктора становится
`<label> failed (<сообщение>)` (собственный label чекера). Публичные конструкторы чекеров не менялись (BC).
Побочный эффект: `HealthCheckerCompletedEvent::$checkerClass` для readiness-only чекеров теперь
`DeferredReadinessCheckerDecorator` (раньше `TimeoutCheckerDecorator`). Чекер, который
поддерживает liveliness (прямой `CheckInterface` без шаблона), по-прежнему конструируется
сразу — его зависимость обязана быть конструируемой.

Регрессия: `tests/Integration/Functional/ReadinessDependencyIsolationTest` (фикстуры
`UnconstructibleDependencyFixture` / `DependentReadinessCheckerFixture`; `TestKernel` принимает
замыкание с доп. сервисами — каждому варианту своё окружение). Фикстура-зависимость должна
реально использоваться в `doCheck()`: иначе Rector (`make fix`) удаляет «неиспользуемое»
свойство вместе с конструктором и тест молча зеленеет без исправления.

## LockStoreDetector проверял неиспользуемые хранилища FrameworkBundle (2026-10-01 UTC)

FrameworkBundle 8.1 (`Resources/config/lock.php`) всегда регистрирует `.lock.flock.store`
(`FlockStore`) и `.lock.semaphore.store` (`SemaphoreStore`), даже если ресурс их не использует.
Детектор брал любой `PersistingStoreInterface` и строил для них чекеры — отсюда
`SemaphoreStore` без sysvsem. `FrameworkExtension::registerLockConfiguration` (7.4–8.1) вешает тег
`lock.store` на каждое хранилище настроенного ресурса: DSN-хранилища —
`.lock.<resource>.store.<hash>` (`Definition(PersistingStoreInterface)` + `StoreFactory::createStore`),
в 8.1 `flock`/`semaphore` — тегом на предопределённый `.lock.flock.store`/`.lock.semaphore.store`.
`CombinedStore` (`ChildDefinition` от `lock.store.combined.abstract`) тега не имеет, но его
составные хранилища тегированы. Правило детектора: скрытые (`.`-префикс) сервисы — только с
тегом `lock.store`; хранилища, зарегистрированные приложением под обычным id, проверяются
как раньше. Если приложение передаёт в `framework.lock` id сервиса-соединения (`\Redis`, DBAL connection, …),
FrameworkBundle строит вокруг него `.lock.<resource>.store.<hash>` с фабрикой
`StoreFactory::createStore(@сервис)`. Чтобы то же соединение не проверялось дважды (свой чекер +
lock store), `HealthCheckerAutoDetectionPass` собирает цели всех обнаруженных чекеров
(аргумент 0, алиасы разрешены) и не регистрирует чекер, чья цель — `StoreFactory::createStore`
вокруг уже проверяемого сервиса. Сервис-хранилище (`RedisStore` и т.п.) передать по id нельзя —
`StoreFactory` принимает только соединения/DSN. Сравнивать фабрику нужно по частям массива:
Rector превращает литерал `[StoreFactory::class, 'createStore']` в `StoreFactory::createStore(...)`
(first-class callable), и сравнение с массивом становится всегда ложным.

## Ревью изоляции readiness и lock store (2026-10-02 UTC)

- **Пароли в выводе проб.** `StoreFactory` и другие фабрики кладут полный DSN в текст исключения,
  а `AbstractReadinessChecker`/`DeferredReadinessCheckerDecorator`/`ElasticaConnectionChecker`
  отдавали его как есть. Теперь всё идёт через `Application\Health\Check\CredentialRedactor`
  (user info в URL и секретные query-параметры); так же и `ParallelAction`
  (`parallel: X failed (...)`).
- **Label при ошибке конструирования** вычисляется в `HealthCheckerDeferredConstructionPass` через
  `ReflectionClass::newLazyGhost()`: в «призрак» кладутся только скалярные promoted-аргументы
  конструктора (с разрешёнными `%параметрами%`), затем вызывается `label()`. Если `label()` трогает
  что-то ещё — инициализатор бросает, берётся fallback `<КороткийКлассЧекера> (<сервис>)`.
- **`ElasticaConnectionChecker` был вне шаблона** (CR-007): статус кластера не помещался в
  `doCheck(): void`. Теперь `doCheck()` возвращает `?string` — деталь печатается как
  `<label> passed (<деталь>)`; Elastica наследует шаблон (`skipped (connections list is empty)`,
  `passed (cluster status: green)`, `failed (<причина>)`), а pass проверяет один
  `AbstractReadinessChecker`. Исключение из `skipReason()` тоже даёт `failed (…)` (Elastica читает
  конфиг клиента именно там).
- `HealthCheckerCompletedEvent::$checkerClass` был классом внешнего декоратора (CR-008) — исправлено,
  см. раздел ниже.
- Пользовательский чекер, объявленный как `ChildDefinition` (без класса до `ResolveChildDefinitionsPass`), не распознавался как readiness-only и создавался сразу (eager). Теперь `HealthCheckerDeferredConstructionPass::flatten()` склеивает цепочку родителей (класс — первый непустой от ребёнка; аргументы родителя, поверх — `index_N` ребёнка) только для классификации и label; сами определения не меняются.
- В текстовом выводе HTTP-проб `warnings` не печатались — теперь есть секция `Warnings:` (в конце,
  после `Messages:`, чтобы не сдвигать строки для существующих парсеров); консольные команды печатают
  `Warnings:` только если они есть.
- RED для `LockStoreSelectionTest` воспроизводится только на FrameworkBundle ≥ 8.1 (CI-lock сейчас
  8.0.x — там предопределённых хранилищ нет): копия в `$TMPDIR` с минимальным профилем +
  `composer require --dev symfony/lock:^8.1`.

## Этап B: события, кеш-пулы, вывод (2026-10-02 UTC)

- **Реальный класс чекера в событиях.** Декораторы бандла (`TimeoutCheckerDecorator`,
  `NonCriticalCheckerDecorator`, `DeferredReadinessCheckerDecorator`) реализуют
  `Checker\CheckerDecoratorInterface::decoratedCheckerClass()`; `@internal Checker\CheckerClass::of()`
  разворачивает цепочку. `Deferred` не может спросить ещё не построенный чекер, поэтому класс передаёт
  `HealthCheckerDeferredConstructionPass` третьим аргументом (класс внутреннего определения за
  Timeout, с разрешёнными `%параметрами%`). Интерфейс — публичный API: пользовательский
  декоратор, реализовавший его, тоже называет настоящий чекер; не реализовавший — называет себя.
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
- **Неровный отступ текстового вывода** (`Messages: \nfirst\n\tsecond`) исправлен:
  заголовок секции на своей строке, каждая строка с `\t`, пустая секция — `Title: none`; так же, как в
  CLI (CLI по-прежнему опускает пустые секции и прячет `Messages` без `-v`). Ответы проб несут
  `Vary: Accept` (формат зависит от Accept).
- **Non-critical + исключение = error** (исправлено). `NonCriticalCheckerDecorator` понижал только
  добавленные ошибки; исключение из `check()` в последовательном `Action` пролетало наружу, в
  `ParallelAction` становилось `parallel: X failed (…)` в `errors`. Теперь декоратор ловит
  `Throwable`, отбрасывает частичный вывод и пишет warning `<класс чекера> failed (<сообщение,
  CredentialRedactor>)`. Критичный чекер по-прежнему бросает наружу в `Action`.
- README раньше советовал тег `healthcheck.checker` и `$result->messages[] = …` для своего
  чекера — оба не работают (тег — FQCN `CheckInterface`, свойства `private(set)`); исправлено.
  Ручная регистрация `CacheChecker` (README «Probing a Local Cache Pool») закреплена тестом
  `CachePoolSelectionTest::testLocalPoolCanBeProbedByRegisteringCacheCheckerExplicitly`.
- RED для `CachePoolSelectionTest::testAppPoolOnRedis…` использует `redis://127.0.0.1:1`
  (мгновенный отказ соединения) — Redis-сервер не нужен, но нужен ext-redis или predis (иначе skip).


## prefer-lowest, level 10 (2026-10-02 UTC)

- **Prefer-lowest (PHP 8.4, Symfony 7.4) проходит.** Что потребовалось:
  - `phpunit/phpunit: >=11.5` в обоих манифестах: 10.5 не знает
    `<source ignoreIndirectDeprecations>` шаблона. Фактический минимум диктует
    `roave/security-advisories` (сейчас 11.5.50).
  - **Реальный баг DBAL 3**: `DBALConnectionChecker` звал `Connection::getServerVersion()`, а в
    DBAL 3.x этот метод `private` → на отключённом соединении readiness всегда падала с
    «Call to private method». Теперь `executeQuery(getDatabasePlatform()->getDummySelectSQL())`
    (есть в 3.x и 4.x); тест на реальном `pdo_sqlite` ловит регрессию на обеих версиях. Ограничение
    DBAL (`^3.0|^4.0`) не менялось.
  - FrameworkBundle в `boot()` регистрирует `ErrorHandler`, если нет `symfony/runtime` (так и в
    7.4/8.x; установка из `composer.json` его не содержит); PHPUnit 11+ помечает такие тесты risky
    («did not remove its own exception handlers»). Это не баг бандла — `TestKernel` снимает обработчики, которые положил
    его `boot()`, в `shutdown()` (сравнение вершины стека до/после через `set_*_handler(null)` +
    `restore_*_handler()`).
- **PHPStan level 10**: две ошибки в `CachePoolDetector` (`mixed` из атрибутов тега) — имя пула
  теперь берётся `poolName()` с сужением через `is_array`/`is_string`, без `@var`.
- `symfony/*-contracts`: в `require` объявлять нечего — из контрактов код использует только
  `HttpClient`, а он нужен лишь опциональному `HttpClientChecker` (приходит с `symfony/http-client`
  из `composer-ci.json`).

## Ревью пропуска lock store и prefer-lowest, фиксы (2026-10-02 UTC)

- **M1 — пропуск lock store поверх проверяемого соединения.** `LockStoreChecker` делает
  `save()`/`delete()` (EVAL на Redis, INSERT в `lock_keys` на DBAL), т.е. проверяет больше, чем
  PING/`SELECT 1` соединения. Store пропускается, только если цель проверяет хотя бы один
  чекер не из `non_critical`, и id чекера store не упомянут ни в `non_critical`, ни в
  `timeouts.overrides` (`HealthCheckerAutoDetectionPass` читает оба параметра расширения сам).
  Решение «переносить критичность» вместо «не пропускать вообще»: дубль на одном и том же Redis
  был причиной самой фичи пропуска.
- **m4.** Знание о форме `framework.lock` ушло в `LockStoreDetector::wrappedTarget()` через
  `WrappedTargetDetectorInterface`; pass не импортирует `StoreFactory`.
  Интерфейс — публичный API для сторонних детекторов (2026-10-04 UTC):
  спек §3.1 запрещает `@internal`-интерфейсы вместо публичного API, а сторонний детектор
  клиента-обёртки иначе дублирует проверку уже проверяемого соединения.
- **m1/m2 `flatten()`.** Цикл `parent` (pass стоит до `ResolveChildDefinitionsPass`) раньше
  съедал память — теперь определение возвращается как есть, ошибку даёт Symfony. Именованные
  аргументы (`$name`) сводятся к позиции через reflection конструктора итогового класса, иначе
  позиционный аргумент родителя побеждал именованный у потомка.
- **s1.** `DBALConnectionChecker` всегда шлёт dummy SELECT: `isConnected()` остаётся `true`, когда
  сервер закрыл долгоживущее соединение (worker mode).
- **Отклонено s3** (label вместо FQCN в `NonCriticalCheckerDecorator`): у `CheckInterface` нет
  публичного `label()` (он `protected` в `AbstractReadinessChecker`, а такие чекеры исключения
  не бросают); новый публичный контракт — материал минорного релиза. FQCN совпадает с форматом
  `parallel: X failed (…)` и сообщений `TimeoutCheckerDecorator`.
- **s5** уже покрыт: `HealthCheckerAutoDetectionPassTest::testProcessPreservesChildCachePoolNameOverParent`.
- **m5.** Порядок элементов класса `TestKernel` выправлен вручную; правило `ordered_class_elements`
  в `.php-cs-fixer.dist.php` включить нельзя — файл `ExactFileRule` в `bundle-standard` (follow-up).
