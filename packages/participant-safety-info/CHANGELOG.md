# Changelog

All notable changes to `phil/participant-safety-info` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `SafetyInfo` and `EmergencyContact`: up to three contacts, allergies, needs and notes of 2000 characters at most,
  never dumped, every field `#[SensitiveParameter]`; no diagnosis field, no list of medications.
- `SafetyInfoCodec`: the plaintext as JSON of version 1, decoded strictly within 32 KiB and a depth of 4.
- `SafetyInfoVault`: the participant submits, reads, copies to another event and erases their own, and sees who read
  it; a responsible person of the event reads it during its access window; a member of the team breaks the glass with
  a reason of 10 to 500 characters; every reading by someone else journaled before the opening, a refusal the same
  whatever its reason; `purge()` once the window has closed, by lots of 1 to 10,000.
- `AccessWindow`, `Role`, `AccessReason`, `AccessRecord` and `SealedSafetyInfo`.
- The ports `Contract\SafetyInfoStore`, `Contract\EventRoles`, `Contract\AccessWindows`, `Contract\AccessJournal`
  and `Contract\SafetyInfoSealer`, with the store and the journal in memory under `Memory\`.
- `KeyManagement\KeyManagementSealer`: the envelope encryption of Symfony's KeyManagement component, the event and
  the participant authenticated with the text.
- The events `SafetyInfoSubmitted`, `SafetyInfoRead` and `SafetyInfoErased`, identifiers only.
- `Exception\InvalidSafetyInfo`, `Exception\AccessDenied` and `Exception\UnreadableSafetyInfo`, under
  `Exception\ParticipantSafetyInfoException`.
- The fuzz target `participant-safety-info-codec`, with its seeds.
- The package skeleton: `composer.json`, `phil-dev.php` and `composer-dependency-analyser.php`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
