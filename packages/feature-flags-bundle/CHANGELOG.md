# Changelog

All notable changes to `phil/feature-flags-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added

- Flags, providers, state store, once-per-request evaluation, reasons and error codes named after the OpenFeature
  specification (`STATIC`, `TARGETING_MATCH`, `SPLIT`, `DEFAULT`, `DISABLED`, `ERROR`; `FLAG_NOT_FOUND`,
  `PARSE_ERROR`, `GENERAL`), without an OpenFeature dependency. An evaluation never throws: a provider or a state
  source that fails gives an `ERROR` evaluation, off, logged on the `feature_flags` channel and memoized for the
  request; `Evaluation::failed()`. `FlagEvaluator` resolves the context on every
  evaluation and memoizes per context, under a memo key built once per context instance and held in a `WeakMap`, so
  a flag read before the firewall loads the user never answers for that user. `FlagEvaluator::definition()`,
  `FlagDefinition::readsAnyOf()` and `Rule::reads()`, the attribute a rule reads. `FlagCatalog` tells an absent
  flag from an unreadable one: `unreadable()`, `lookup()` and `UnreadableEntry`.
- Targeting (membership, stable percentage rollouts).
- A/B experiments and exposure counting.
- Metadata and expiry.
- Audited toggle and editor, revisions, conflicts.
- A flag per circuit of `phil/circuit-breaker-bundle`, `circuit-open.<key>`, inactive and of kind `ops`, declared by
  `ForceOpenFlagsPass`; `FlagCircuitOverride`, tagged `phil_circuit_breaker.override`, forces the circuit open while
  its flag is on.
- Database store (Doctrine DBAL, schema listener), tested on SQLite, MySQL 8 and PostgreSQL. A stored row that no
  longer reads is unreadable, not absent: its flag evaluates as `PARSE_ERROR`, and `DiagnosableFlagProvider` lists it
  as an `UnreadableFlag` with its reason.
- Controller attribute, route condition, Twig functions, tested in a kernel. A route condition refuses, with
  `FlagNotRoutable`, a flag with a rule or an experiment on the subject or the tenant, which it would evaluate before
  authentication; such a flag guards its controller with `#[IsFeatureEnabled]`.
- Listing command with `--fail-on-expired`, tested in a kernel; it names each unreadable flag with its reason.
- Admin screens, same-origin guard, tested in a kernel. The list and a page of its own show an unreadable flag with
  its reason; it is neither toggled, edited, nor created again under its name. The flag page reports only the outcomes `toggled`, `created`
  and `edited` of `done`, never other text from the query string.
- `FeatureFlagsDataCollector`, which keeps what a request evaluated as a `FlagsReport`, and its profiler template.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
