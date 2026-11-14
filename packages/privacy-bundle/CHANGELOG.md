# Changelog

All notable changes to `phil/privacy-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-14

### Added

- `#[PersonalData]`, `DeclaredPersonalFields`, `PersonalField`, read through parents by `Lineage`.
- `ProcessingRegister`, `RegisterEntry` and `ProcessingActivity`: the register of processing, its activities drawn
  from the rules of the retention schedule that hold personal information, with their purpose, recipients, processors
  and location, its fields drawn from `#[PersonalData]`, and its gaps named: an activity without a purpose or a
  location, a field that names a rule the schedule does not hold.
- `DataSubject`, `DataCategory`, `ModuleName`, `ErasureReport`.
- `RetentionPeriod`, `RetentionRule`, `RetentionSchedule`, `PurgeOrder` and the enums; `Processing` and
  `Processor`, what a rule declares for the register of processing.
- `Denylist`, `UrlRedactor`, `CommandLineRedactor`, `ContextRedactor`. `UrlRedactor` matches every declared token
  path prefix with one pattern, the longest first, so the token of each is redacted whatever the order of
  declaration; `CommandLineRedactor` reads an option value in runs, without backtracking, so a 1 MB value is
  redacted and the rest of the string kept.
- `#[Anonymize]` and `#[AsAnonymizer]`, `DeclaredAnonymizations` and `AnonymizedField`.
- The `Anonymizer` contract, under `Domain\Contract`, `AnonymizationContext`, `AnonymizerOptions` and the
  `Anonymizers` registry, with thirteen built-in types: `email`, `firstname`, `lastname`, `name`, `phone`, `address`,
  `iban`, `date_shift`, `constant`, `null`, `hash`, `lorem` and `pattern`.
- `LocalePack`, `LocalePacks` and the packs `fr_CA`, `en_CA`, `fr_FR`, `en_US`, `en_GB`, `en_AU`, `de_DE` and
  `es_ES`, whose telephone patterns are fictional ranges only.
- `ColumnRule`, `AnonymizationPlan` and `DatabaseIdentity`.
- `PersonalDataExporter`, `PersonalDataEraser`, `RetentionScheduleSource`, `RetentionPurger` (ports), with every other
  interface of the bundle under `Application\Contract`.
- `SubjectExport`, `SubjectErasure` and `RetentionRun`, whose `orders()` says what a run would purge.
- `RetentionJob`, one run at a time; `ConfiguredRetentionSchedule`, the schedule of the configuration; the message
  `RunRetention` and `phil:privacy:retention` (`--confirm`). A job line without a purger stops a run before anything
  is purged.
- `AccountDeletions` and `DeletionWorkflow`: an account deleted in stages on `symfony/workflow`, with a delay during which
  it can be cancelled, an export, then the erasure, under one lock per person; `AccountDeletion`, `DeletionPlace`,
  `AccountDeletionBusy`; the identifier-only messages `AdvanceAccountDeletion` and `ProcessDueAccountDeletions`;
  `phil:privacy:deletions`; the schedule `phil_privacy`.
- `AccountDeletionStore`, `ExportArchive`, `ConsentProofs`, `CurrentDataSubject` and `DataSubjectUser` (ports);
  `DbalAccountDeletions`, `DbalConsentProofs`, `PrivacyTables`, `PrivacySchemaListener`, `EncryptedExportArchive`,
  `LoggedConsentProofs` and `SecurityCurrentDataSubject`; `UtcMoments`, every instant of the tables in a
  `datetime_utc_immutable` column, written in UTC in the platform's format and read back in that format only.
- Consent to cookies: `ConsentCategory`, `ConsentPolicy`, `ConsentChoice`, `ConsentState`, the signed `ConsentToken`,
  `ConsentRecorder`; a page and a banner on which refusing is as simple as accepting, working without JavaScript;
  the Twig functions `privacy_consent()`, `privacy_consent_pending()` and `privacy_consent_banner()`; the Stimulus
  controller, served by AssetMapper under `@phil/privacy-bundle` when AssetMapperBundle is registered; the Global Privacy Control; a dated proof of the version of the text; `Vary` on what reads a consent;
  the decision `grant`, which adds one category to those already granted.
- The cookie inventory: `DeclaredCookie`, `CookieStorage`, `CookieGroup` and `CookieInventory`, read from
  `config/privacy/cookies.yaml` at the build, each category checked against the consent policy, with the cookie of
  the consent declared by the bundle; the banner, the page of the choices, the cookie policy at
  `/privacy-choices/cookies` and the Twig function `privacy_cookie_policy()` drawn from it; `UndeclaredCookies`;
  `UndeclaredCookieListener`, which logs a response that sets an undeclared cookie, in debug by default; the test
  helper `AssertsDeclaredCookies`.
- Third-party frames held until consent: the Twig function `privacy_embed()`, which reads the address with `Url` of
  `phil/url`, an HTTPS address of a host name, and turns YouTube and Vimeo addresses into their players that write
  fewer trackers with `CookielessVideo`, with a preview served by the site, a button that grants the category and,
  with the Stimulus controller `embed`, one that loads one frame only.
- Exports as a ZIP archive with a readable `README.txt` and one JSON file per module: `ZipFile`, written without an
  extension, `ExportPackage`, `ExportReadme` (port), `PlainExportReadme` and `TwigExportReadme`. A member's download,
  the answer to a request without an account and the export before an account deletion are archives.
- A member's export and erasure from their preferences, on by default: `PreferencesController`, `PersonalDataDownload`,
  the confirmation by Symfony's sudo mode, and `PrivacyRights`: turning a right off takes a full written sentence and
  warns at every build and before every command; the `rights` keys and the documentation carry a `DANGER` caution.
- Access and erasure requests from people without an account, off until `guest_requests.enabled`: `GuestRequests`,
  `GuestRequest`, `GuestRequestKind`, `TooManyGuestRequests`; `GuestRequestStore`, `GuestLinkSender` and
  `GuestSubjects` (ports); `DbalGuestRequests`, which seals the address, `MailerGuestLinkSender`, which sends at once
  through the mailer's transports, and `EmailGuestSubjects`; `GuestRequestController`. A link proven by a single-use
  secret of 256 bits, of which only the hash is kept; a limit per client and one per address, keyed on a keyed hash;
  the same answer whether an address is known or not; a page that acts only through its form.
- The register of confidentiality incidents: `Incident`, `IncidentRegister` (port), `DbalIncidentRegister`, which
  answers for its store in a retention schedule, and the commands `phil:privacy:incident` and
  `phil:privacy:incidents`.
- Each member's consent to tracked emails, off until `email_tracking.enabled`: `EmailTrackingChoice`,
  `EmailTrackingConsents`, which answers `opens()` and `clicks()` for a member under the current version of the text and
  is the module `email_tracking` of the export and the erasure, `EmailTrackingStore` (port), `DbalEmailTrackingConsents`
  and the table `phil_privacy_email_tracking`; a section of the preferences page with one box per purpose, both
  unticked, and the route `phil_privacy_email_tracking`.
- `MountingRouteLoader`, which mounts the pages in front of the application's routes, and
  `@PhilPrivacyBundle/config/routes.php` for an application that mounts them itself.
- `AnonymizationTarget` (port); `AnonymizationRun`, `AnonymizationReport` and `ProductionGuard`;
  `AnonymizationRefused`.
- `DbalAnonymizationTarget`; `ConnectionIdentity`, which reads the identity the guard checks from a DBAL connection
  without connecting; `AnonymizationPlanReader`, which draws the plan from the ORM mapping, the `categories` and the
  `tables` of the configuration.
- `PhilPrivacyBundle`: the keys `log_redaction`, `retention_schedule_source`, `retention`, `account_deletion`,
  `consent`, `cookies`, `guest_requests`, `incidents`, `email_tracking`, `rights`, `pages`, `register`, `database` and `anonymization`;
  `CookieServices` and `GuestRequestServices`; the exporter, eraser, anonymizer and locale pack tags by autoconfiguration;
  `AnonymizationServices` and `DatabaseServicesPass`, which removes the database services when the connection is
  not a service.
- `RedactingProcessor`, wired from configuration and tagged `monolog.processor`.
- `phil:privacy:export`, `phil:privacy:erase` and `phil:privacy:register`, which fails with `--strict` while the
  register has gaps. `phil:privacy:export --output` creates
  its file `0600` whatever the umask, refuses an existing file unless `--force` is given, checks the write, and
  exits with a failure — removing the file it created — when it could not write the answer.
- `phil:privacy:anonymize` (`--dry-run`, `--strict`, `--seed`, `--confirm`).
- The documentation in `doc/`, one file per topic, indexed by the README; `doc/encrypted-columns.md` on encrypting
  personal data at rest with `#[Encrypted]` and `#[BlindIndexed]` of Symfony's KeyManagement component;
  `doc/email-tracking.md`; `doc/legal-framework.md`, the rules the bundle is built around and what its defaults do
  about each.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
