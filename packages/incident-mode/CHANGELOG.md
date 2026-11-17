# Changelog

All notable changes to `phil/incident-mode` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.0-alpha - 2026-11-15

### Added

- `IncidentRestrictions`: whether a restriction applies to a member now — it does when an incident suspends it,
  unless the restriction is one the configuration spares established members from and the member is one;
  `read-only` spares a member only when it is spared too; a visitor, a null member, is never spared. The spared
  restrictions are checked as restriction names when it is built.
- `Contract\EstablishedMembers`, who is an established member, by the application's identifier as a string, and
  `NoEstablishedMembers`, the default that holds nobody for established.

## 0.1.0-alpha - 2026-11-13

### Added

- `Restriction`: one thing an incident suspends, by name; the common ones as constants; `read-only` covering every
  restriction, the application's own included.
- `Duration`, from one minute to ninety days: no incident lasts without an end.
- `Preset` and `Presets`: named restrictions with a proposed and a longest duration; five built-in presets — suspend
  sign-ups, invitations, new messages, every new contribution, read only —; the application's added, replacing a
  built-in one in its place, or leaving one out.
- `Actor`, a stable identifier that refuses an e-mail address.
- `Activation` and `LiftReason`: an incident in force from its start until its end or its lifting, its restrictions
  copied from the preset.
- `IncidentMode`: `start()` with a required duration bounded by the preset, `extend()` within it, `lift()`,
  `liftIfDue()` and `liftExpired()` recording the expiry once, `isSuspended()`, `suspended()`, `inForceNow()`, the
  journal; nothing kept between calls.
- The events `IncidentStarted`, `IncidentExtended` and `IncidentLifted`, each sent once.
- `Contract\ActivationStore` with conditional lifting and extension, `InMemoryActivationStore`.
- `Dbal\DbalActivationStore`, `Dbal\IncidentTables` and `Dbal\IncidentSchemaListener`: one table on PostgreSQL, MySQL,
  MariaDB or SQLite, its moments in UTC, added to the schema the ORM generates.
- `Contract\LiftScheduler`, `NoLiftScheduler`, and `Messenger\MessengerLiftScheduler` with `LiftIncident`, delayed
  until the end, and `LiftExpiredIncidents`, the recurring sweep, with their handlers.
- Actors found and forgotten, for the export and the erasure of personal data.
- A benchmark of the check a page makes.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`, and the documentation in `doc/`.
