# Changelog

All notable changes to `phil/community-notes` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `Note`, a note beside a resource — its text, its author, its status and the decision that gave it —, `NoteStatus`,
  `Rating` and `Verdict`.
- `CrossAgreementRule`: enough ratings, a verdict holding its share of them, and enough pairs of raters who usually
  disagree giving it together, each rater in one pair at most, compared in integers; `PairHistory`, `Bridge`,
  `Decision`, with `toArray()` and `toPublicArray()` without the raters' names, and `DecisionReason`.
- `CommunityNotes`: `write()`, `rate()`, `withdraw()`, `reconsider()`, `get()`, `find()`, `notesOf()`, `shownOf()`,
  `exportMember()` and `eraseMember()`, every rating deciding the note again.
- The ports `Qualification`, `NoteRepository`, `RatingRepository` and `RatingHistory`; stores in memory.
- `AuthorizationQualification`, the qualification asked of Symfony Security, and `QualificationSubject`, what the
  application's voters decide on.
- The events `NoteWritten`, `NoteRated`, `NoteStatusChanged`, `NoteWithdrawn` and `NoteErased`, each naming the note;
  the message `ReconsiderNote` and its handler.
- `CommunityNotesException`, implemented by `InvalidNote`, `NoteNotFound` and `NoteRefused`.
- A benchmark of the rule.
- The package skeleton: `composer.json`, `phil-dev.php` and `composer-dependency-analyser.php`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
