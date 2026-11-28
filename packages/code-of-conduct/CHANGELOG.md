# Changelog

All notable changes to `phil/code-of-conduct` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `CodeVersion` and `ChangeKind`: the versions of the code of a scope, numbered from 1, major or minor, the first one
  major, a title of 200 characters and a text of 100,000 at most, hashed by SHA-256.
- `CodeOfConduct`: publishes the next version, records the acceptance of the version in force only, and answers the
  `AcceptanceStatus` of a member — no code, not accepted, outdated after a major version, accepted — with
  `mayTakePart()`.
- `Acceptance`, with the hash of the text the member was shown.
- `Reports`: files a report, named or anonymous, naming 20 members at most, refused when no responsible person outside
  the people named could receive it; the receipt of the reporter; the queue, the reading, the notes, the review, the
  resolution with an `Outcome`, the dismissal and the stepping aside of the responsible people of the scope who are
  not named; the same refusal whether a report does not exist or may not be seen.
- `Report`, `ReportStatus` and its moves, `Outcome`, `ReportNote`, `ReportView`, `NoteView`, `ReportReceipt` and
  `ReportPage`; texts never dumped.
- The ports `Contract\CodeVersionStore`, `Contract\AcceptanceStore`, `Contract\ReportStore`,
  `Contract\ResponsiblePeople` and `Contract\TextSealer`, with the stores in memory under `Memory\`.
- `KeyManagement\KeyManagementTextSealer`: the envelope encryption of Symfony's KeyManagement component for the text
  of a report and its notes, bound to the report and the note.
- The events `CodeVersionPublished`, `CodeAccepted`, `ReportFiled`, `ReportViewed`, `ReportNoteAdded`,
  `ReportStatusChanged` and `ReportRecused`, identifiers only.
- `Exception\InvalidCodeOfConduct`, `Exception\ActionRefused`, `Exception\ReportUnavailable` and
  `Exception\UnreadableText`, under `Exception\CodeOfConductException`.
- The package skeleton: `composer.json`, `phil-dev.php` and `composer-dependency-analyser.php`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
