# Changelog

All notable changes to `phil/certificates-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.1-alpha - 2026-11-17

The next version is 0.2.1-alpha.

### Changed

- It accepts `phil/open-badges` 0.3 beside 0.2.

## 0.2.0-alpha - 2026-11-15

The next version is 0.2.0-alpha: `CertificateRepository` gains a method, and the schema two columns.

### Added

- `CourseCompletions`, the port a course system answers: whether a member completed a course, as the opaque
  identifier of that completion or `null`; registered by the application under the interface, none by default.
- `Course::$completionSource`, the key of the course in the system its completions are asked of, given to
  `Course::define()` and `Courses::define()`, changed by `changeCompletionSource()`, kept by `revise()`; `OpaqueId`,
  the rules of such keys and identifiers.
- `Certificate::$completion`, the identifier of the completion it was awarded on, kept as its evidence; one
  certificate for each completion of a course.
- `Awarding::award()` asks `CourseCompletions` for a course that names a completion source, under the lock of the
  status index, and refuses the award with `CertificateRefused` without a holder, without a source configured, without
  a completion or for a completion already awarded; `Awarding::awardOnCompletion()`, for a listener of the
  application's own completion event, gives back the certificate already awarded on the completion instead.
- `phil:certificates:course --completion-source`, `phil:certificates:award --on-completion` and the completion printed
  by the award and by `phil:certificates:status`.
- The documentation page [Completion sources](doc/completion-sources.md).

### Changed

- `CertificateRepository::findByCompletion()`: a repository of the application's own implements it. The tables gain
  `phil_certificates_course.completion_source` and `phil_certificates_certificate.completion`, nullable, with a unique
  index on the course and the completion; a generated migration adds them.
- `phil/open-badges` 0.2 is required, the version that signs and verifies through `web-token/jwt-library`, and
  `phil/open-badges-bundle` 0.1.1.

## 0.1.0-alpha - 2026-11-14

### Added

- `Course`, a course or a training known by its slug, with its description, the narrative of its criteria, its
  `PassCriteria` — a minimum score, a minimum of minutes, modules to complete —, its validity in months and the image
  of its badge, the credits of continuing education its recertification asks for and the moment it ends; retired and
  reopened.
- `Certificate`, awarded for a course: its `VerificationCode`, the name printed on it, its period of validity, its
  status index, the signed credential, whether it is listed, its holder by the member's stable identifier and the
  certificate it renews; never valid past the end of its course; revoked once, with a reason; its `CertificateStatus`
  valid, expired or revoked, an expired certificate keeping its status bit clear; the days left before its end and the
  moment its holder was last reminded.
- `Courses`, which defines, revises, retires and reopens courses; `Awarding`, which checks an `Attainment` against the
  criteria and awards the certificate with an Open Badges credential signed as `phil/open-badges-bundle` is configured,
  the recipient's e-mail address or URL kept only as a salted hash, the status index taken and the certificate stored
  under a lock of `symfony/lock` when there is a lock factory, the award started again, three times at most, when the
  storage finds its index taken (`StatusIndexTaken`); `Awarding::recertify()`, which awards a certificate linked to
  the one it renews, to the identity the first was issued to, by the credits of continuing education its holder
  gathered since or by an attainment, valid from the end of the first when that end is to come; `Revocation`.
- `ExpiryReminders`, which dispatches `CertificateExpiring` once for each threshold of `reminders.days` a certificate
  reaches before its end, neither revoked nor renewed, by pages and up to a limit per run.
- `LearningActivity`, `Credits`, `MemberId` and `Period`, and `ContinuingEducation`, which records the activities of
  continuing education of each member — once for each source —, removes, lists and sums them over a period and toward
  a course, and gives a holder's progress toward its recertification.
- `CertificateAwarded`, `CertificateRenewed` and `CertificateRevoked`, dispatched once the certificate is stored,
  carrying its identifier, its code and its course's slug, and the certificate renewed; `CertificateExpiring`, with the
  days left; `LearningActivityRecorded`, with the activity's and the member's identifiers.
- `StatusListLayout` and `CertificateStatusLists`: the status indexes split into lists of a fixed size, published by
  `phil/open-badges-bundle` with the revoked certificates' bits set; other lists asked of the application's own.
- `CertificatePdf`: the certificate drawn on a landscape page with the native writer, its texts translated, a QR code of
  its page in the register, the signed credential embedded by Gotenberg's `attach` step as a PDF/A-3 associated file of
  relationship `Data`, the PDF repaired to PDF/A-3b when asked, and an optional signature; each print names the subject
  `certificate`, which a site that checks the licenses of its prints lets through by its own configuration.
- The register's routes: the open courses and a lookup by code, each certificate's page with its status, its PDF and
  its credential — gone once revoked —, each course's page with the certificates listed, paginated; unlisted
  certificates are not indexed; the certificates a certificate renews and that renew it, a course's end and the
  credits of its recertification; in English and French.
- The Doctrine mapping of courses, certificates and activities of continuing education and their repositories, behind
  `CourseRepository`, `CertificateRepository` and `LearningActivityRepository`, the credits summed by the database,
  the entity manager reset through the `doctrine` registry when a certificate's index is taken; `CertificateUrls`, the
  URLs a certificate points to.
- The commands `phil:certificates:course`, which defines or revises a course, `phil:certificates:award`,
  `phil:certificates:recertify`, `phil:certificates:revoke`, `phil:certificates:status` (text or JSON),
  `phil:certificates:export`, which writes the signed credential alone for a validator or a wallet,
  `phil:certificates:remind`, `phil:certificates:credit`, which records or removes an activity, and
  `phil:certificates:credits` (text or JSON).
- The configuration `phil_certificates`: the clock, the lock factory, the thresholds of the reminders, the status
  lists, the page size, fonts, embedding, archival profile and signature of the PDF, the register's layout; the
  bundles it builds on checked when the container is built.
- The documentation in `doc/`, one file per topic, indexed by the README, with where 1EdTech conformance testing
  stands, how to verify a certificate today, the procedure of validation against 1EdTech's conformance validator, and
  an offline check of the exported credentials' shape against what Open Badges 3.0 describes, without any file of
  1EdTech.
- The fuzz target `verification-code`, the benchmark `CertificatesBench`, and an integration test of the PDF against a
  real Gotenberg.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
