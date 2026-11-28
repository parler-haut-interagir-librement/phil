# Changelog

All notable changes to `phil/content-access` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `AccessPolicy`, `Audience` and `WhenDenied`: public, members or restricted by a rule of `phil/rules`, an early access
  public for good from an instant, a preview of the part before the cut, a locked or hidden denial; contradictions
  refused with `InvalidPolicy` when the policy is built.
- `AccessDecider`, `Decision`, `Access` and `Reason`: the whole content, the preview, a locked entry or nothing, at the
  clock's instant; whether the decision is personal, when it changes for everyone, and the leaves of the rule that
  explain what remains.
- `Viewer` and `AccessFacts`: a stable identifier, roles, a verified email domain and a network address, given to rules
  under reserved names beside the application's own facts; `ReservedFact` refuses a fact under one of those names.
- The condition types `access.member`, `access.role`, `access.feature` — with `after_days`, an opening by seniority, and
  `for_days`, a rolling duration — and `access.institution`, by verified email domain or IPv4 and IPv6 ranges,
  registered by `AccessConditions::types()`.
- `Grant` and the `Entitlements` port, with `InMemoryEntitlements` and `ChainEntitlements`: grants read only for a
  signed-in viewer and only when a rule asks for a feature.
- `PreviewCut`: the blocks of a UJO (Universal JSON Output) document a decision lets through, with no public part for a
  document without a cut.
- `StructuredData`: the schema.org `isAccessibleForFree` and `hasPart` of a reserved content, the same for every viewer.
- The versioned JSON form, `AccessPolicyReader` and `AccessPolicyWriter`: strict reading with a size bound, canonical
  writing that reads back to itself, and the fingerprint of a policy.
- The exceptions `ContentAccessException`, `InvalidPolicy`, `InvalidViewer`, `InvalidGrant` and `ReservedFact`.
- The fuzz target of the policy reader and the benchmark of reading and deciding.
- The package skeleton: `composer.json`, `phil-dev.php` and `composer-dependency-analyser.php`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
