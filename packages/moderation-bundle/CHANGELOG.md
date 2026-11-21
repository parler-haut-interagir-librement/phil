# Changelog

All notable changes to `phil/moderation-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.3.0-alpha - 2026-11-20

The incident mode is that of `phil/incident-mode-bundle`, which the queue offers when
it is registered. See [Upgrading from 0.2](doc/incident-mode.md#upgrading-from-02).

### Added

- The Flex recipe in `recipe/0.3`, without the `incident` key, which this version refuses; `recipe/0.1` keeps serving
  the versions 0.1 and 0.2.
- `Infrastructure\IncidentMode\IncidentProposal`: the link to the page of the incidents of `phil/incident-mode-bundle`
  for the role of its administration, and the proposal of an incident when reports surge and none is in force.
- `ModerationServices::INCIDENT_ROLE_PARAMETER`.
- Functional tests of a sign-up, an invitation and a form refused through the checkpoints of
  `phil/incident-mode-bundle` while an incident suspends them.

### Changed

- The incident mode is offered when `phil/incident-mode-bundle` is registered with its administration on, and its
  role, `phil_incident_mode.admin.role`, decides who sees it; `phil/incident-mode` installed alone no longer is.
- `AdminController` takes an `IncidentProposal` in place of the role and the panel of the incidents.
- `ModerationPrivacy` no longer takes the store of the incidents: the incidents a member started or lifted are
  answered by `phil/incident-mode-bundle`.

### Removed

- The `incident` key of the configuration — `role`, `presets`, `without`, `spare_established` —, now refused.
- The services of `phil/incident-mode` the bundle registered: the store and its schema listener, the presets,
  `IncidentMode`, the lifting by Messenger and its handlers, `IncidentRestrictions` and the alias of
  `EstablishedMembers`.
- `IncidentPanel`, `CurrentMemberRestrictions`, the pages `phil_moderation_incidents`, `phil_moderation_incident_start`
  and `phil_moderation_incident_lift`, their template and their EasyAdmin routes, and
  `ModerationAdmin::incidentsMenuItem()`.
- The Twig function `phil_moderation_suspended()`: `phil_incident_suspended()` of `phil/incident-mode-bundle` answers.
- The `incidents` of a member's export, and `phil_incident_activation` in the effects of the erasure.

## 0.2.0-alpha - 2026-11-18

Members, targets and the identifiers of the five tables compare byte by byte on MySQL
and MariaDB. See [Upgrading from 0.1](doc/installation.md#upgrading-from-01).

### Changed

- `ModerationTables::configure()` and `ModerationTables::tables()` take the platform of the connection, and the schema
  listener gives it: on MySQL and MariaDB, the identifiers of the rows and decisions, `target_type` and `target_id`,
  and the members — `reporter`, `moderator`, `author`, `member`, `recipient` — take the `utf8mb4` character set and
  the `utf8mb4_bin` collation. PostgreSQL and SQLite keep their default, which compares bytes already.

### Security

- Under the default case-insensitive collation of MySQL and MariaDB, a member whose identifier differed from another's
  only by case read the statements of reasons addressed to the other, the moderation read their notes together, a
  member left the sandbox for both, and deciding on a target closed the open reports of a target that differed from it
  only by case: the stores select by member and target in SQL.

## 0.1.0-alpha - 2026-11-15

### Added

- `MemberId`, a stable identifier that refuses an e-mail address, and `TargetReference`, a type and an identifier.
- `ModerationTarget`, the contract each package implements for its content, registered by autoconfiguration;
  `ProfileTarget` over the application's `MemberProfiles`.
- `Reports`: one report per member and target while it is open, reasons from the configuration, reports of the bundle
  for held, flagged and hidden content.
- Roles of the moderation in the configuration: the first held, the target types each covers.
- `Decisions`: dismiss, approve, hide, restore, remove, warn; applied by the target's package, closing the open
  reports; the history per target and per author.
- `StatementsOfReasons`: a decision that hides, removes or warns comes with a statement of reasons to the author — the
  reason, the terms or the law it rests on (`ReasonGrounds`), the facts, whether a report or automated detection
  prompted it, the ways to contest it (`Redress`) —, its `Grounds` checked before the action is applied; the member's
  pages of their statements; the notification through `phil/notifications-bundle` when the configuration names its
  type.
- `ModerationQueue`: one queue for every registered type, by the role of the moderator.
- `Submissions`: the hook of the watched words (port `WordScreen`) and of the first-post sandbox (port
  `SandboxPolicy`, `FirstPostSandbox` by default), left on the first approval.
- `TemporaryHides`: hiding by trusted members (port `TrustedHiders`), reviewed by the moderation.
- `MemberNotes`, the notes of the moderation on a member.
- `PublicJournal`: the public decisions with their role, never the moderator.
- `ReportSurge`, and the incident mode wired and proposed when `phil/incident-mode` 0.2 is installed, with its
  `IncidentRestrictions`, which spares the established members of its port `EstablishedMembers` from the restrictions
  configured; `CurrentMemberRestrictions` asks it for the member of the current request.
- The events `ReportFiled`, `DecisionTaken`, `ContentHeld`, `ContentHiddenByMember` and `StatementIssued`.
- Five DBAL tables, their moments in UTC, added to the schema the ORM generates.
- The pages of the moderation, the report form, the public journal and the statements of reasons of a member, drawn
  with recipes of the PHIL UX kit, in English and French; the same pages grafted on every EasyAdmin dashboard.
- `TokenCurrentMember` and `TokenCurrentModerator`, which read `ModerationMember`, never the user identifier.
- `ModerationPrivacy`, the export and the erasure through `phil/privacy-bundle`.
- A Flex recipe, a benchmark, and the documentation in `doc/`.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
