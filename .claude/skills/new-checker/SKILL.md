---
name: new-checker
description: Scaffold a new health checker in the Msstc4Symfony HealthCheckBundle — checker class, unit test, and (when needed) an auto-detection scan in `HealthCheckExtension::process()`. Trigger when the user asks to add support for a new infrastructure service (Pulsar, Kafka, ClickHouse, etc.), wants a custom application-level check, or types `/new-checker`. The slash-command argument, if any, is the base name (e.g. `/new-checker Pulsar`).
---

The user wants a new checker scaffolded. The argument that followed `/new-checker` (if any) is the base name — e.g. `Pulsar` → `PulsarChecker`.

## What to do

Delegate the actual scaffolding to the `checker-author` subagent. Do **not** scaffold inline — the subagent encodes the project's conventions (file paths, namespaces, exception discipline in `check()`, PHPUnit-10 attribute style, compiler-pass scan patterns, the `phpstan` / `phpunit` finish line) and the result is cleaner when it has an isolated context window.

Before dispatching, make sure four facts are known. If the conversation already supplies them, pass them through; otherwise ask the user in a single round:

1. **Checker base name** (verbatim from the slash-command argument when present).
2. **Mode** — readiness, liveliness, or both.
3. **Detection model** — user-supplied autoconfigured implementor, or auto-detected via container scan (and which kind of scan: class FQN / service-id regex / tag).
4. **Underlying probe** — the cheap call that proves liveness on the target client (`ping`, `isConnected`, etc.).

Do not invent these. A bare name without mode / detection model is not enough to scaffold correctly — ask.

## Dispatch

Call the `checker-author` subagent with a prompt that:

- Quotes the four answers above.
- States explicitly: write the checker class, the unit test, and (for auto-detected) the compiler-pass scan; then run `vendor/bin/phpstan` and `vendor/bin/phpunit --filter <Name>CheckerTest`; report files changed.
- Includes any extra constraints the user mentioned (specific library version, custom exception types, non-default file path, etc.).

## After the subagent returns

Relay to the user:

- The list of files created / modified.
- The detection model chosen.
- The phpunit summary line.

Do not commit. If `phpstan` flagged something the subagent could not resolve without baselining, surface that to the user and ask how they want to proceed — never extend `phpstan-baseline.neon` silently.
