# Changelog

All notable changes to `phil/guardian-consent` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-24

### Changed

- Requires the latest minor of each dependency (`symfony/service-contracts` 3.8).

## 0.1.0-alpha - 2026-11-20

### Added

- `CalendarDate` and `LeapDayRule`: days of the calendar, read from `YYYY-MM-DD` or from an instant in a time zone,
  their anniversaries, with 29 February on 1 March or on 28 February in a common year, and the whole years completed.
- `Jurisdiction` and `Jurisdictions`: the age of consent, the age of majority, the time zone and the leap-day rule of
  a place named by its ISO 3166 code, with Quebec — 14 and 18 — as the default.
- `AgeAssessment` and `AgeBracket`: a member's age from a date of birth that is not kept, below the age of consent,
  from it to majority, or adult.
- `MemberId` and `GuardianId`, stable identifiers that refuse an `@`; `Guardian`, `Feature` and `NoticeVersion`.
- `GuardianConsent`: `register()`, `minor()`, `status()`, `allows()`, `request()`, `open()`, `decide()`, `revoke()`,
  `handOver()` and `sweep()`, with consent feature by feature, refused by default, five requests waiting at most, a
  configurable lifetime and retention, and the handover on the day of the age of consent.
- `ConsentStatus`, `Minor`, `ConsentRequest`, `ConsentProof`, `Decision`, `Actor` and `SweepReport`.
- `ConfirmationToken` and `TokenHasher`: signed, single-use confirmation links whose verifier is stored as its
  HMAC-SHA-256 under the application's key, with key rotation.
- The ports `MinorStore`, `RequestStore`, `ProofStore`, `Notifier`, `GuardianLink` and `SubjectRights`; the stores in
  memory, which implement `ResetInterface`; `NoGuardianLink`; and `GuardianConsentRights`, the access and erasure of
  what the library keeps on a member or a guardian.
- The events `ConsentRequested`, `ConsentDecided`, `ConsentRevoked`, `RequestErased`, `HandoverDue` and
  `HandoverCompleted`, carrying identifiers only.
- `GuardianConsentException`, implemented by `InvalidIdentifier`, `InvalidConsent`, `InvalidConfiguration`,
  `UnknownJurisdiction`, `InvalidToken`, `ExpiredRequest`, `ConsentNotRequired` and `NotAGuardian`.
- A fuzz target for the reader of confirmation tokens.
- The package skeleton: `composer.json`, `phil-dev.php` and `composer-dependency-analyser.php`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
