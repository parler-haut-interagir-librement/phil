# Changelog

All notable changes to `phil/checkin-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `PhilCheckinBundle` and the `phil_checkin` configuration: the endpoints turned on, the clock, the store of
  check-ins, the Doctrine connection, the catalog, the policy, the admission rules and the bounds of offline devices,
  among them the strength a code needs to be carried by a manifest, 64 bits by default.
- `CheckinServices`: the services tagged `phil_checkin.credential_resolver` asked together, a `CredentialResolver` of
  the application tagged by autoconfiguration; the policy; the rules, each checked when the container compiles; the
  desks of the checkpoints; the synchronisation over the catalog named, or over the services tagged
  `phil_checkin.credential_catalog`, which autoconfiguration tags; the ledger aliased as `CheckInLedger`.
- `CredentialCatalogs`, the tagged catalogs asked together in their order, and `CredentialCatalogPass`, which turns the
  offline services off when no catalog is named or tagged: a module plugs its admission in — tickets, registrations,
  memberships — with one service that is both a resolver and a catalog, configured by nobody.
- Admission rules in Symfony's ExpressionLanguage: `AdmissionRules` and `CredentialAttributes`, the contracts of the
  rules and of what they read; `ConfiguredAdmissionRules`, `NoCredentialAttributes`; `CheckpointRules`, which
  evaluates a rule on `credential`, `attributes`, `checkpoint` and `now`, refusing on any error; `RuledCredentials`,
  which closes the checkpoint to a credential its rule refuses and leaves it out of the manifest; `CheckpointDesks`,
  the desk and manifest issuer of a checkpoint, the rule applied to entries only.
- `CheckInRecorded`: each scan the endpoints record, online or from a journal, dispatched to the application's event
  dispatcher with its identifiers, direction, outcome and conflict.
- `DoctrineCheckInLedger`: the check-ins in the table `phil_checkin_scan`, keyed by scan and unique by credential and
  sequence, the attendance folded by two queries for any number of credentials; `CheckInSchemaListener` adds the
  table to the schema the ORM generates.
- `CheckinController` and `config/routes.php`: the scan, manifest and synchronisation endpoints on the desks of the
  checkpoints, answered only when turned on, to the desk controller of the site, on a checkpoint the application's
  security grants `PHIL_CHECKIN_SCAN`, never cached, with their errors as `{"error"}`, `weak_codes` for a manifest of
  codes too weak to travel with its key.
- `CheckinRequest` and `CsrfProtectionPass`: the controller's header, fetch metadata and the stateless CSRF token
  `phil_checkin`, listed among FrameworkBundle's; a container without CSRF protection stops with what to change.
- `DeskConfig` and `Routes`: the configuration of the desk controller for a checkpoint.
- The Stimulus controller `checkin-desk`: codes from a field or from `phil-checkin:code`, the result announced and
  dispatched as `checked`, each device offline on its own without a relay on the site, decisions from a manifest
  found by the HMAC-SHA256 of the code with WebCrypto, codes it does not hold offered to the page by
  `phil-checkin:lookup`, the attendance answered online kept for offline, a journal sent once online and applied again
  to a fresh manifest, labels in English and French, loaded on the pages that use it (`"fetch": "lazy"`); its
  stylesheet, both served by AssetMapper through its own extension.
- The device's store: IndexedDB, then `localStorage`, then the memory of the page; the manifest written once and each
  scan in one transaction; a write refused said to the staff; what `localStorage` kept moved to IndexedDB once it
  opens, a journal joined scan by scan; its limits documented.
- The browser test of the desk in Chrome with Panther: online, offline from IndexedDB, synchronised once back; the DOM
  test of the controller under Node's test runner.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
