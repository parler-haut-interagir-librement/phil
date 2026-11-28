# Changelog

All notable changes to `phil/audience-segments` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `Segment`: a named rule of phil/rules on the `member` fact, with an opaque identifier; condition types that guess
  traits, score behaviours or read openings and clicks refused by the words of their names.
- `SegmentJson`: the query language shared by the API and a builder, the versioned JSON form of phil/rules with the
  segment's name, read strictly, written canonically, with the fingerprint of its rule.
- The audience condition types `ticket.holder`, `date.participant`, `selection.member`, `purchase.made`,
  `supporter.active` and `person.tagged`, registered by `AudienceConditions` for the ports given, with
  `PurchaseFilter`.
- The ports `Audience`, `Exclusions`, `Participation`, `Purchases`, `Supporters`, `PeopleTags` and `MembershipStore`,
  checked for order, size and identifiers by `PortContractViolated`.
- `Segments`: one member with the reason in `Membership`, bounded pages of a dynamic segment in `MemberPage`, and
  `FrozenList`, the members at a date with the rule's fingerprint, read by pages; the global exclusions removed before
  every rule, full computations bounded by `SegmentTooLarge`.
- `Recalculation` and `RecalculationReport`: the stored members compared side by side with the audience, changes
  written by batches, `MemberEntered` and `MemberLeft` published with identifiers only, the date of the run recorded.
- The exceptions `AudienceSegmentsException`, `InvalidSegment`, `SegmentTooLarge` and `PortContractViolated`.
- The benchmark of freezing a segment.
- The package skeleton: `composer.json`, `phil-dev.php` and `composer-dependency-analyser.php`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
