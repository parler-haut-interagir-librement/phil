# Changelog

All notable changes to `phil/privacy-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.3.0-alpha - 2026-11-24

The erasure can run as a workflow, one step per module through phil/outbox-bundle,
`ErasureEffect::Anonymised` tells a skeleton judged non-identifiable from a pseudonymised record, and the register of
confidentiality incidents holds the eight paragraphs of section 7 of the Québec *Regulation respecting confidentiality
incidents* (chapter A-2.1, r. 3.1), keeps its corrections, is kept at least five years (section 8), and drafts in
Markdown the notices to the Commission, their follow-ups, and the notices to each person and in public (sections 3 to
6), which the person in charge reviews and sends, with a journal of the communications to a third party, the notices
put off for an investigation and the consultations of the person in charge (sections 3.5 and 3.7 of chapter P-39.1);
every `Sec-GPC` header of a request is read. See [Erasure as a workflow](doc/erasure-workflow.md),
[The register of incidents](doc/incidents.md) and [The drafts of the notices](doc/incident-notices.md).

To update: a `GuestRequestStore` of your own implements `retain()`. Code that reads the register's JSON, or the
rows of `RegisterEntry::toArray()`, finds `personal_data_left` added, and the Markdown register a column more. Review
the stores your erasers report as `Pseudonymised`: keep it for a pseudonym, a token or a keyed hash, which stay
personal data, and report `Anonymised` only for a skeleton someone judged non-identifiable. To turn the workflow on,
install phil/outbox-bundle on the connection of the bundle, route `EraseModule` to an asynchronous transport, set
`erasure_workflow.enabled: true`, and run `doctrine:migrations:diff`, or the SQL of
[The tables](doc/erasure-workflow.md#the-tables), for `phil_privacy_erasure_request` and `phil_privacy_erasure_step`.
For the register of incidents, run `doctrine:migrations:diff`, or the SQL of [The tables](doc/incidents.md#the-tables),
which adds nine columns to `phil_privacy_incident`, nullable or false by default so that its lines are kept, and creates
`phil_privacy_incident_revision` and `phil_privacy_incident_journal`. A fixed retention period shorter than five years
on `phil_privacy_incident` now fails the container build: raise it to `P5Y` or more. An `IncidentRegister` of your own
that also purges keeps at least five years from the discovery. Code that reads the JSON of `phil:privacy:incidents`
finds the new fields, and its Markdown ten columns more, a table of corrections and a table of the journal;
`phil:privacy:incident` prints the paragraphs a line still lacks.

### Added

- Erasure as a workflow, `erasure_workflow` (`enabled`, `lease`, `operator_role`, `store`), off by default:
  `Application\Erasure\ErasureRequests`, which opens a request for every module of the erasure inventory or for the
  modules named, a module the inventory does not declare refused, and keeps the inventory's version on it; one
  `phil_privacy.erase_module` outbox record per module, written in the transaction of the request, carrying the
  request's identifier and the module's name, with the idempotency key `<request>:<module>`; one step per module
  (`pending`, `running`, `completed`, `needs_attention`), unique for the pair and leased to one worker, so that a
  message delivered twice or a request resumed never runs a finished module again; the request's state drawn from
  its steps (`running`, `needs_attention`, `completed`), and `failed` left to an operator.
- `Domain\Erasure\ErasureInventory`, `ErasureRequest`, `ErasureStep`, `ErasureProgress`, `RequestState`,
  `StepState` and `AttentionReason` (`retries_exhausted`, `unknown_module`, `subject_unavailable`).
- `Application\Contract\ErasureRequestStore` and `Infrastructure\Database\DbalErasureRequests`, over the tables
  `phil_privacy_erasure_request` and `phil_privacy_erasure_step` of `PrivacyTables`, which also purges the closed
  requests of the store `phil_privacy_erasure_request`; the SQL of both tables for MySQL, MariaDB and PostgreSQL in
  [The tables](doc/erasure-workflow.md#the-tables).
- `Application\Contract\ErasureMessages` and `Infrastructure\Outbox\OutboxErasureMessages`, which records the
  message of each module in the outbox of phil/outbox-bundle.
- `Application\Message\EraseModule`, `Handler\EraseModuleHandler` and `Exception\ModuleErasureFailed`, which names the
  request and the module, never the module's failure; `Infrastructure\Outbox\EraseModuleCodec`, which skips the
  record of a request that is gone, and `EraseModuleRelay`, which hands the message to the bus.
- `Infrastructure\Messenger\ErasureFailureListener`: on `WorkerMessageFailedEvent`, it waits while `willRetry()` is
  true, then marks the step and the request as needing attention; `ErasureRequests::replay()` and a message replayed
  from the failure transport bring the request back to running, and `ErasureRequests::fail()` closes it as failed.
- `Application\Contract\ExternalPersonalDataEraser`, tagged `phil_privacy.external_eraser`: the erasure sent to an
  external service with the step's stable idempotency key, the step ending only on `ErasureRequests::acknowledge()`.
- The progress of an erasure request, `phil_privacy_erasure_progress` at `/erasure/requests/{id}` and its `.json`,
  shown to the person, to a user granted `erasure_workflow.operator_role`, and for a request made without an account
  to the holder of its progress link's secret; anyone else gets a 404. `Infrastructure\Http\ErasureProgressController`
  and the templates `erasure/progress` and `guest/erasure_started`.
- `phil:privacy:erasure`: a request module by module, `--replay`, and `--fail --confirm`.
- `GuestRequests::erasesAsAWorkflow()`, `startErasure()` and `WORKFLOW_RETENTION`, and `GuestRequestStore::retain()`:
  with the workflow on, an opened erasure link opens an erasure request that keeps the identifier of the request made
  without an account, never the address, which the worker resolves through `GuestSubjects`; that request is kept, its
  link no longer opening it, until the erasure is closed or for 90 days at most.
- An optional `ErasureRequests` argument, last, for `AccountDeletions` and `GuestRequests`: the erasure stage of a
  deletion opens one erasure request under the deletion's identifier and closes the deletion once it completed.
- `ErasureEffect::Anonymised`, for a skeleton judged non-identifiable, and `ErasureEffect::leavesPersonalData()`;
  `personal_data_left` in the register's JSON and a column of its Markdown, and the effects that leave personal data
  marked by `phil:privacy:erase`.
- [Choosing a control](doc/choosing-a-control.md): which tool answers which problem and where it stops, tier 1 named,
  the token vault ruled out, the transparent encryption of the database server and the verification of backups.
- `MisconfiguredPrivacy::erasureLease()`.
- The paragraphs of section 7 of the Québec *Regulation respecting confidentiality incidents* (chapter A-2.1, r. 3.1) on
  `Domain\Incident\Incident`: `information` and `undescribedReason` (paragraph 1, never both), `occurredUntil` and
  `occurrenceApproximate` (paragraph 3), `discoveredUntil` (paragraph 4), `affectedApproximate` (paragraph 5),
  `riskAssessment` (paragraph 6), `publicNoticeAt` and `publicNoticeReason` (paragraph 7), each text at most
  `MAX_TEXT` characters and each period never ending before it starts; `missingParagraphs()`, which names the
  paragraphs a line does not answer yet, and `knownSince()`, the end of the discovery or its date.
- `Domain\Incident\PublicNoticeReason` (`increased_injury`, `excessive_hardship`, `no_contact_details`,
  `prompt_action`), the cases of section 6 of the regulation, and `replacesIndividualNotice()`, false for a public
  notice given to act quickly.
- `Incident::update()` corrects the summary and the dates of the discovery and of the incident, and empties by name a
  date or a number recorded by mistake (`clear`, `CLEARABLE`).
- `Application\Contract\IncidentHistory` and `Domain\Incident\IncidentRevision`: `DbalIncidentRegister` keeps what each
  field held before a correction in `phil_privacy_incident_revision`, in the transaction of the save, and purges it with
  its line.
- `Incident::MINIMUM_RETENTION` (`P5Y`, section 8 of the regulation) and `RetentionPeriod::lastsAtLeast()`: a fixed
  period shorter than five years on `phil_privacy_incident` fails the container build with a message that cites the
  section, and `DbalIncidentRegister::purge()` never removes a line known for less than five years, whatever the cutoff
  it is given.
- `phil:privacy:incident` takes `--discovered-until`, `--occurred-until`, `--approximate-occurrence`, `--information`,
  `--undescribed-reason`, `--approximate-affected`, `--risk-assessment`, `--public-notice`, `--public-notice-reason`
  and `--clear`, and names the paragraphs a line still lacks; `phil:privacy:incidents` prints every field,
  `missing_paragraphs` and the corrections, in Markdown and in JSON.
- `phil:privacy:incident-notice <incident> --to=commission|subject|public [--locale=fr|en] [--output=file]`: a draft
  in Markdown of the notice to the Commission (section 3 of the regulation), of the notice to each person (section 5)
  or of the public notice (section 6), filled from the register and from the organisation; each rubric the register
  does not know stays in place marked to complete and is listed after the draft. The draft goes to the standard output
  or to a new file readable by its owner only; nothing is sent, nothing is recorded, and the draft is never logged.
- `--follow-up`, with `--since`: the follow-up to the Commission of section 4, with the rubrics of section 3 corrected
  after the notice, read from `IncidentHistory`.
- `--to=public` refused for an incident without a public notice reason, with the three cases of section 6; the
  command reminds when the notice to each person is still owed, and the command that records the date of the notice.
- `Application\Incident\IncidentNotices`, `IncidentNoticeDraft`, `Exception\NoticeRefused`, and
  `Domain\Incident\NoticeRecipient`, `NoticeRubric` and `NoticeOrganisation`.
- `Application\Contract\NoticeOrganisationSource`, and `Infrastructure\LegalNotices\SiteIdentityOrganisation`, which
  reads the publisher, its registration and the privacy officer from the identity of the site of
  phil/legal-notices-bundle when it is installed.
- `Infrastructure\Twig\TwigIncidentNotice` and the templates `@PhilPrivacy/incident_notice/`, with their texts in
  French and in English in the domain `phil_privacy`.
- [The drafts of the notices of an incident](doc/incident-notices.md): sections 3 to 6 of the regulation, the rubrics
  filled and those left to complete, who decides and who sends.
- The journal of an incident (sections 3.5 and 3.7 of chapter P-39.1): `Domain\Incident\JournalEntry` and
  `JournalKind` (`third_party_communication`, `notice_deferral`, `deferral_lifted`, `officer_consultation`), numbered
  per incident from 1 and only ever added to; `Application\Contract\IncidentJournal`, implemented by
  `DbalIncidentRegister` over the table `phil_privacy_incident_journal`; `Application\Incident\IncidentJournalRecorder`
  (`record()`, `entries()`, `isDeferred()`), which refuses an unknown incident, an entry before the start of the
  discovery or ahead of time, a lift without a deferral in force, and a register without a journal, with
  `Application\Exception\JournalRefused`; `phil:privacy:incident-journal`, which adds an entry or prints the journal.

### Changed

- The comment of `ErasureEffect::Pseudonymised` says that the record is still personal data for whoever holds the
  link back, instead of a record without anything naming the person.
- `GuestRequestStore` has `retain()`.
- The documentation of `SubjectErasure` no longer shows a previous exception on `ErasureIncomplete`.
- `PrivacyTables::configure()` adds the columns it lacks to a `phil_privacy_incident` already in the schema, and
  creates `phil_privacy_incident_revision` and `phil_privacy_incident_journal`.
- `DbalIncidentRegister` takes the clock as an optional second argument, the clock of `pages.clock` in the container;
  its purge counts from the end of the discovery when it is a period.
- With `--id`, the summary given to `phil:privacy:incident` replaces the one the line had, and `--discovered` and
  `--occurred` correct its dates.
- `Incident::pendingNotifications()` counts a public notice given for a reason that replaces the notice to each person
  as that notice.
- [The register of incidents](doc/incidents.md) and [The legal framework](doc/legal-framework.md) cite sections 3.5 to
  3.8 of the *Act respecting the protection of personal information in the private sector* (chapter P-39.1) and
  sections 6 to 8 of the regulation, with the SQL of the tables for MySQL 8.4, PostgreSQL 17 and SQLite.
- `phil:privacy:incidents` prints the journal of each line, in a table of its own in Markdown and under `journal` in
  JSON, and the purge of `DbalIncidentRegister` removes the journal of a line with it.

### Fixed

- `ConsentContext::sendsGlobalPrivacyControl()` reads every occurrence of `Sec-GPC`, each split on its commas and
  trimmed: a browser that sent the signal in a second header, or in a list such as `Sec-GPC: 0, 1`, was asked for its
  consent and could have optional categories granted.
- Requires the latest minor of each dependency (`twig/twig` 3.31, `monolog/monolog` 3.13,
  `symfony/event-dispatcher-contracts` 3.8), and of each development requirement (`doctrine/orm` 3.8, `doctrine/dbal`
  4.6, `doctrine/persistence` 4.2, `symfony/dom-crawler` 8.2, …).

## 0.2.0-alpha - 2026-11-20

The link of a request without an account points to `public_origin`, and the `prod`
environment refuses to boot, while those requests are on with the mailer sender, without it or
`framework.trusted_hosts`. See [Where the link points](doc/requests-without-an-account.md#where-the-link-points).

### Added

- `public_origin`: the canonical address of the site, such as `https://example.org`, that the links sent by email
  point to; `Infrastructure\Routing\GuestLinkOrigin`, which generates a link while the router's context names that
  origin and gives the request's context back at once, the origin and the links read by `phil/url`.
- `PhilPrivacyBundle::boot()` and `PhilPrivacyBundle::MAILED_LINKS`.
- `Application\Exception\AccountErasureIncomplete`, what `AdvanceAccountDeletionHandler` throws when an erasure
  fails: the identifier of the request and the names of the modules, without a previous exception.
- The documentation of a failure transport of the privacy messages, with a short retention and
  `messenger:failed:remove` once the request is closed, in [Deleting an account](doc/account-deletion.md).
- The Flex recipe in `recipe/0.2`: the bundle and `config/packages/phil_privacy.yaml`, with `public_origin` read from
  `APP_PUBLIC_ORIGIN`, null while it is unset, and a denylist of the logs.

### Changed

- In the `prod` environment, while `guest_requests` is on with the mailer sender, the bundle refuses to boot when
  neither `public_origin` nor `framework.trusted_hosts` is set. The origin is read when the kernel boots: one with a
  path, a query, a fragment or an IP address is refused, and `localhost` is accepted only in debug mode.
- `MailerGuestLinkSender` takes a `GuestLinkOrigin`, last and optional.
- `phil:privacy:erase` prints the class of a module's failure, and its message only with `-v`.
- The Rotation section of [Encrypting personal data at rest](doc/encrypted-columns.md) cites `rotate($scope)` of the
  data key store and `key-management:rewrap-data-keys`, and says that access to the KMS decrypts every column.

### Security

- The link of a request without an account was built on the `Host` header of the request: a request naming another
  host sent the person a genuine link to that host, and one click handed its secret over.
- Privacy: `ErasureIncomplete::after()` chained the exception of the first failing eraser as the previous exception,
  and `AdvanceAccountDeletionHandler` let it through to Messenger, which kept its message in the `ErrorDetailsStamp`
  of the envelopes sent to the retry and failure transports, and in its logs: a duplicate-key error could keep a
  person's address there after the erasure. Neither chains it any more.
- Privacy: `phil:privacy:erase` printed the message of a module's failure, which can quote the person's data.
- Privacy: the documentation advised rewriting encrypted values by loading and saving the entities. That encrypts
  nothing again, Doctrine's unit of work finding the decrypted values equal, and a master key removed afterwards
  destroyed the data still sealed under it. The advice is removed.

## 0.1.1-alpha - 2026-11-18

The locks of the export, the erasure and the retention run are renewed between their
steps, and the keys of the tables compare byte for byte on MySQL and MariaDB. See
[Locks and long work](doc/export-and-erasure.md#locks-and-long-work).

### Added

- `HeldLock`, a lock of `symfony/lock` renewed between the steps of a work, and `LockLost`, thrown when a renewal
  finds the lease expired, taken by another process, or unconfirmed by its store.
- An optional `HeldLock` argument for `SubjectExport::export()`, `SubjectErasure::erase()` and `RetentionRun::run()`,
  renewed before each module or purge order; under it, a lost lock stops the work before the next step, and is never
  counted as the failure of a module.
- An optional platform argument for `PrivacyTables::configure()`, given by `PrivacySchemaListener`.

### Fixed

- `GuestRequests`, `AccountDeletions`, `PersonalDataDownload` and `RetentionJob` renew their lock before each module of
  an export or an erasure, before each purge order, and before each write of a request or a place: a work that lasted
  longer than the lock's lifetime let a second worker take the expired lock and run the same export, erasure or purge
  at the same time. A work whose lock was lost stops before its next step, logs a warning without naming the person,
  and keeps the request at its last saved place for the worker that holds the lock now: `access()`, `erase()` and
  `of()` answer `null`, `advance()` the place read again, and `RetentionJob::run()` `null`.
- On MySQL and MariaDB, the identifiers, the people, the export references and the token hashes of the tables take
  `utf8mb4_bin`: the default collation, which folds case and accents, let the people `Alice` and `alice` share a
  deletion request and a choice about tracked emails. An existing schema needs the migration that
  `doctrine:migrations:diff` writes.

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
