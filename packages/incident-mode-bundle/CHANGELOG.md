# Changelog

All notable changes to `phil/incident-mode-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.2-alpha - 2026-11-25

### Changed

- The development requirements accept `phil/privacy-bundle` 0.3, the minor version of its main branch and no earlier
  one, so that the package installs on its own beside that bundle.
- Requires the latest minor of each dependency (`doctrine/dbal` 4.6, `twig/twig` 3.31, `symfony/ux-twig-component`
  3.8, `symfony/translation-contracts` 3.8, …), and of each development requirement (`doctrine/orm` 3.8,
  `doctrine/doctrine-bundle` 3.4, `symfony/ux-toolkit` 3.8, `twig/html-extra` 3.31).
- The development requirements take `twig/html-extra` 3.31.1 or later, which fixes the advisories of its sandbox
  published on 2026-11-24.

### Fixed

- The compiler pass that removes Messenger handlers runs before the one of
  `symfony/messenger`, which Symfony 8.2.0-RC1 registers in its own bundle at the default priority: the container no
  longer fails to build with a handler locator that names a removed service.

### Security

- Requires `twig/twig` 3.31.1 or later, which fixes the advisories of its sandbox published on 2026-11-24.

## 0.1.1-alpha - 2026-11-20

### Added

- `IncidentModeServices::ADMIN_ROLE_PARAMETER`, the container parameter `phil_incident_mode.admin.role`, set to the
  role of the administration while it is on: another bundle offers the page of the incidents to that role alone.
- `IncidentModeServices::DEFAULT_SCHEDULE_NAME` and `DEFAULT_SCHEDULE_FREQUENCY`, which `IncidentSchedule::DEFAULT_NAME`
  and `DEFAULT_FREQUENCY` now repeat.

### Fixed

- Without `symfony/scheduler`, building the container failed on the interface of `IncidentSchedule`, whose constants
  the configuration read for its defaults, even with `lifting.schedule` turned off: the defaults live on
  `IncidentModeServices`.

## 0.1.0-alpha - 2026-11-18

### Added

- `PhilIncidentModeBundle` and its configuration `phil_incident_mode`: the connection, the presets — the built-in ones,
  the application's own, some replaced or left out, their durations written `90m`, `2h` or `1d` and checked when the
  container is built —, the restrictions established members are spared from, the services of the members, the
  layout, the kit, the time zone, the status of a refusal, the administration, the lifting, the audit and the privacy.
- The services of `phil/incident-mode`: `DbalActivationStore` on the configured connection, `IncidentSchemaListener`
  on the ORM's schema of that connection, `Presets`, `IncidentMode` with the application's clock and event
  dispatcher, `IncidentRestrictions` with the port `EstablishedMembers` aliased to `NoEstablishedMembers` until the
  application points it at its own service.
- `DurationInput`: a duration as a person writes it, `90`, `45m`, `2h`, `3 days`, `1d 12h`, bounded, with a fuzz
  target; an amount of minutes, hours or days; a duration in its largest whole unit.
- `Checkpoint` and the port `CurrentMember`, with `SecurityCurrentMember`: whether a restriction applies to the member
  of the request, the suspensions with their end, and `guard()`, which throws `SuspendedByIncident` — an HTTP error,
  503 or the configured status, with `Retry-After`; a visitor answered from one read of the incidents in force, with a
  benchmark.
- `#[SuspendedDuring]` on a controller or its class, for every method or those named, and the route default
  `_suspended_during`, checked by `CheckpointListener`, which renders a refusal as the page of the suspension.
- `SuspendedDuringTypeExtension`: the option `suspended_during` of every form, which makes a submission invalid while
  a restriction it names applies, with the error that says until when.
- `SuspensionPage`: the refusal page, the public status page and its notice fragment, never kept by a shared cache;
  the banner `@PhilIncidentMode/_banner.html.twig`; `IncidentModeExtension`, the Twig functions
  `phil_incident_suspended`, `phil_incident_suspensions`, `phil_incident_sentence`, `phil_incident_moment`,
  `phil_incident_preset`, `phil_incident_restriction`, `phil_incident_duration` and `phil_incident_kit`;
  `IncidentWording`, what is suspended and until when in the visitor's language and the configured time zone, which
  it names.
- `IncidentAdminController` and `AdminRequest`: the administration page, for the configured role — the incidents in
  force, extended within their preset or lifted, the presets with their proposed and longest durations, the journal a
  page at a time —, every form posted with a stateless CSRF token, a refused form shown again with what went wrong;
  built from the recipes of the PHIL UX kit or of the Shadcn kit, named after the Toolkit's subdirectory.
- The commands `phil:incident:start`, `phil:incident:extend`, `phil:incident:lift` — with `--expired`, the sweep of a
  cron job — and `phil:incident:list`, with the journal and the presets, the moments in UTC.
- The automatic lifting: `MessengerLiftScheduler` on the configured bus and the handlers of the library, and
  `IncidentSchedule`, the recurring sweep of Symfony Scheduler; `IncidentModePass` falls back on the sweep of the
  command when the bus is not there.
- `AuditIncidents`, the incidents written to the trail of phil/audit-bundle when it is registered, without their
  reason; `IncidentPrivacy`, the incidents a member started or lifted exported and erased through phil/privacy-bundle
  when it is registered.
- Translations in English and French, the domain `phil_incident_mode`.
- The Flex recipe in `recipe/0.1`: the bundle, its configuration and the routes of the administration and of the status
  page.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
