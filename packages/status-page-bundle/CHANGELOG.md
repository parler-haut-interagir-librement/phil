# Changelog

All notable changes to `phil/status-page-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `PhilStatusPageBundle` and its configuration `phil_status_page`: title, source, components, records, history, cache,
  layout, kit subdirectory and snapshot.
- The domain: `ComponentStatus` and its order, `IncidentState`, `Component`, `Incident` with its updates,
  `ScheduledMaintenance`, `StatusPage`, and `InvalidStatusRecord` and `UnknownSnapshotTarget` under
  `StatusPageException`.
- `StatusPageBuilder`, which puts the page together at the clock's present from a source of states and a store of
  records.
- The sources of states: `DeclaredStatuses`, and `MonitoringStatuses`, which reads the checks, tasks and maintenance
  mode of `phil/monitoring-bundle`, detected by `MonitoringPass`, and keeps the states in a cache pool.
- `RecordsReader` and the stores of records: `ConfiguredRecords` and `JsonFileRecords`, with the fuzz target
  `status-records`.
- The page at `/status`, its JSON at `/status.json` and its Atom feed at `/status.atom`, served by
  `StatusPageController` without script, cookie nor session; `JsonDocument`, `AtomFeed` and `TwigPageRenderer`.
- The templates, drawn with the Card and Badge recipes of the PHIL UX kit, the Twig functions `phil_status_kit()` and
  `phil_status_variant()`, and the translations in English and French.
- The snapshot — `index.html`, `status.json`, `feed.atom` — written by `SnapshotPush` and given to the targets through
  `SnapshotPublisher`: `DirectoryPublisher`, `FlysystemPublisher`, or a publisher of the application.
- `phil:status-page:push`, the message `PushStatusSnapshot` and its handler, and the schedule `phil_status_page`.
- The Flex recipe, under `recipe/`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
