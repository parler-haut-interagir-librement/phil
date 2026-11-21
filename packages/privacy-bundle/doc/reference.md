# API reference

Every type under `Phil\PrivacyBundle\`, by layer. "Implemented" means implemented and covered by unit tests.

## Bundle

| Type | Role | State |
|---|---|---|
| `PhilPrivacyBundle` | `AbstractBundle`, alias `phil_privacy`; registers `RedactingProcessor`, autoconfigures the exporter and eraser ports, registers `SubjectExport`, `SubjectErasure`, `ProcessingRegister` and their three commands, the anonymization services through `AnonymizationServices`, and the rights, the consent, the cookie inventory, the requests without an account, the incidents and the retention job through `RightsServices`; `ANONYMIZER_TAG`, `LOCALE_PACK_TAG`, `DATABASE_IDENTITY` | Implemented |

## Domain

### `Domain\Attribute`

| Type | Role | State |
|---|---|---|
| `PersonalData` | Property attribute: `$category` (`DataCategory`), `$retention` (`?string`, a rule identifier) | Implemented |
| `Anonymize` | Property attribute: `$type`, `$options` (`AnonymizerOptions`); `TYPE_PATTERN` | Implemented |
| `AsAnonymizer` | Class attribute registering an application's `Anonymizer` under `$type` | Implemented |

### `Domain\Declaration`

| Type | Role | State |
|---|---|---|
| `DeclaredPersonalFields` | `of($class)` and `uncoveredIn($class, ?$schedule)`: reads declarations, parents first | Implemented |
| `PersonalField` | One declared property: `$class`, `$property`, `$category`, `$retention`; `isCoveredByRetention()`, `isCoveredBy($schedule)` | Implemented |
| `DeclaredAnonymizations` | `of($class)`: the `#[Anonymize]` declarations by property, parents first | Implemented |
| `AnonymizedField` | One declared property: `$class`, `$property`, `$anonymize` | Implemented |
| `Lineage` | `properties($class)`: the properties of a class and its parents, each on its declaring class | Implemented |

### `Domain\Anonymization`

| Type | Role | State |
|---|---|---|
| `AnonymizationContext` | `$options`, `$locale`, `$random`, `$salt`; `pick()`, `fill($pattern)`, `digest($value)` | Implemented |
| `AnonymizerOptions` | Named scalar options; `has()`, `string()`, `int()`, `scalar()` | Implemented |
| `Anonymizers` | The anonymizers by type; `builtIn()`, `with()`, `get()`, `types()` | Implemented |
| `Anonymizer\EmailAnonymizer`, `PersonNameAnonymizer`, `PhoneAnonymizer`, `AddressAnonymizer`, `IbanAnonymizer`, `DateShiftAnonymizer`, `ConstantAnonymizer`, `NullAnonymizer`, `HashAnonymizer`, `LoremAnonymizer`, `PatternAnonymizer` | The thirteen built-in types | Implemented |
| `NamePart`, `AddressPart` | Enums: the part of a name, the `part` option of `address` | Implemented |
| `ColumnRule` | One column: `$table`, `$column`, `$type`, `$options`, `$origin` | Implemented |
| `AnonymizationPlan` | The rules by table, a later rule replacing an earlier one; `tables()`, `isEmpty()`, `$uncovered` | Implemented |
| `DatabaseIdentity` | `$platform`, `$host`, `$name`; `describe()` | Implemented |
| `Locale\LocalePack` | Names, streets, cities, regions, postal codes, fictional telephone ranges, country, address format; `CODE_PATTERN` | Implemented |
| `Locale\LocalePacks` | The packs by code; `builtIn()`, `with()`, `get()`, `codes()` | Implemented |
| `Locale\BuiltInLocales` | `all()`: `fr_CA`, `en_CA`, `fr_FR`, `en_US`, `en_GB`, `en_AU`, `de_DE`, `es_ES` | Implemented |

### `Domain\Contract`

| Type | Role | State |
|---|---|---|
| `Anonymizer` | `anonymize($value, $context)`: one non-null value to one that tells nothing | Port; the built-in anonymizers of `Domain\Anonymization\Anonymizer` |

### `Domain\Subject`

| Type | Role | State |
|---|---|---|
| `DataSubject` | Opaque person identifier; `fromString()`, `equals()`, `Stringable`; `MAX_LENGTH = 255` | Implemented |
| `DataCategory` | Lowercase `snake_case` classification; `fromString()`, `equals()`; `MAX_LENGTH = 64` | Implemented |
| `ModuleName` | Name a module answers subject requests under; `fromString()` | Implemented |
| `ErasureReport` | One module's effects by store; `of($module, $effects)`; never empty | Implemented |

### `Domain\Redaction`

| Type | Role | State |
|---|---|---|
| `Denylist` | Normalised exact keys and `*` patterns; `of()`, `fromList()`, `denies()`, `keys()` | Implemented |
| `UrlRedactor` | Query strings, URL user information, declared token path segments; `redact()`; `PLACEHOLDER = '[redacted]'` | Implemented |
| `CommandLineRedactor` | Values of denied long options and declared short options; `redact()` | Implemented |
| `ContextRedactor` | Message and context walker combining the three; `redactMessage()`, `redactContext()`; `PLACEHOLDER` | Implemented |

### `Domain\Retention`

| Type | Role | State |
|---|---|---|
| `RetentionPeriod` | `fixed()`, `untilEvent()`, `indefinite()`, `notYetFixed()`; `isFixed()`, `cutoff($now)` in UTC with month clamping, `lastsAtLeast($minimum)` | Implemented |
| `RetentionPeriodKind` | Enum: `Fixed`, `UntilEvent`, `Indefinite`, `NotYetFixed` | Implemented |
| `RetentionRule` | One schedule line; refuses a job on an indefinite period and `AwaitsExpiry` without a fixed period; `ID_PATTERN`; `holdsPersonalInformation()`; `$processing` | Implemented |
| `Processing` | What the register says of a store: `$purpose`, `$recipients`, `$processors`, `$location`; `isComplete()` | Implemented |
| `Processor` | A service provider and where it keeps the records; `toString()` | Implemented |
| `RetentionSchedule` | The lines by identifier; `has()`, `rule()`, `rules()`, `purgeOrders($now)`, `jobsWithoutCutoff()` | Implemented |
| `PurgeOrder` | `$ruleId`, `$store`, `$cutoff` | Implemented |
| `Mechanism` | Enum: `Job`, `Ttl`, `Rotation`, `Event`, `Manual` | Implemented |
| `ErasureEffect` | Enum: `Deleted`, `Anonymised` (a skeleton judged non-identifiable), `Pseudonymised` (still personal data for whoever holds the link back), `Redacted`, `AwaitsExpiry`, `Survives`; `leavesPersonalData()`, false for `Deleted` and `Anonymised` only | Implemented |
| `AccessResponse` | Enum: `Returned`, `AsCategory`, `NotReturned` | Implemented |

### `Domain\Erasure`

| Type | Role | State |
|---|---|---|
| `ErasureInventory` | The modules whose erasers are declared, and whether each is external; `of($local, $external)`, `has()`, `isExternal()`, `select($modules)`; `$version`, a fingerprint of 16 hexadecimal digits; `VERSION_PATTERN` | Implemented |
| `ErasureRequest` | One erasure request: a person or the request without an account it came from, the inventory's version, the state; `forSubject()`, `forGuest()`, `isAbout()`, `isOpenedBy($token)`; `ID_PATTERN`, `TOKEN_PATTERN` | Implemented |
| `ErasureStep` | One module of a request; `pending()`, `keyOf($request, $module)`, `idempotencyKey()` | Implemented |
| `ErasureProgress` | A request and its steps, as the page and the JSON give them; `counts()`, `toArray()` | Implemented |
| `RequestState` | Enum: `Running`, `NeedsAttention`, `Completed`, `Failed`; `isClosed()` | Implemented |
| `StepState` | Enum: `Pending`, `Running`, `Completed`, `NeedsAttention` | Implemented |
| `AttentionReason` | Enum: `RetriesExhausted`, `UnknownModule`, `SubjectUnavailable` | Implemented |

### `Domain\AccountDeletion`

| Type | Role | State |
|---|---|---|
| `AccountDeletion` | One request; `isOpen()`, `isDue()`, `isCancellable()` | Implemented |
| `DeletionPlace` | `requested`, `exported`, `erased`, `cancelled` | Implemented |

### `Domain\Consent`

| Type | Role | State |
|---|---|---|
| `ConsentCategory` | A declared category; `label()`, `description()` | Implemented |
| `ConsentPolicy` | The categories and the version of the text; `optional()`, `isNecessary()`, `retain()` | Implemented |
| `ConsentChoice` | A dated choice on one version; `grants()` | Implemented |
| `ConsentState` | What is allowed for a visitor; `isGranted()`, `isPending()`, `granted()` | Implemented |
| `ConsentToken` | The signed value of the cookie; `encode()`, `decode()` | Implemented |

### `Domain\EmailTracking`

| Type | Role | State |
|---|---|---|
| `EmailTrackingChoice` | A member's dated choice about tracked emails on one version of the text; `allowsOpens()`, `allowsClicks()`, `toArray()` | Implemented |

### `Domain\Cookie`

| Type | Role | State |
|---|---|---|
| `DeclaredCookie` | One line of the inventory; `matches()`, `isFamily()`, `isSession()`, `purpose($locale)` | Implemented |
| `CookieStorage` | Enum: `Cookie`, `LocalStorage`, `SessionStorage` | Implemented |
| `CookieInventory` | The declared cookies; `fromArray()`, `with()`, `declares()`, `undeclared()`, `ofCategory()`, `namesOf()`, `groups()` | Implemented |
| `CookieGroup` | The cookies of one category, or of the consent itself | Implemented |

### `Domain\GuestRequest`

| Type | Role | State |
|---|---|---|
| `GuestRequest` | A request without an account; `issue()`, `hash()`, `isExpired()`, `isOpenedBy()` | Implemented |
| `GuestRequestKind` | Enum: `Access`, `Erasure` | Implemented |

### `Domain\Incident`

| Type | Role | State |
|---|---|---|
| `Incident` | A line of the register of incidents, with the eight paragraphs of section 7 of the Québec regulation (r. 3.1); `record()`, `update()` with `clear`, `isClosed()`, `pendingNotifications()`, `missingParagraphs()`, `knownSince()`, `MINIMUM_RETENTION`, `CLEARABLE` | Implemented |
| `IncidentRevision` | What a line held before a correction: the field, its previous value, the correction's number and instant | Implemented |
| `PublicNoticeReason` | Enum: `IncreasedInjury`, `ExcessiveHardship`, `NoContactDetails`, `PromptAction`; `replacesIndividualNotice()` | Implemented |
| `JournalEntry` | An entry of the journal of an incident: its number, kind, when it was done, who, what and why, the expected end of a deferral, when it was written; `numbered()` | Implemented |
| `JournalKind` | Enum: `ThirdPartyCommunication`, `NoticeDeferral`, `DeferralLifted`, `OfficerConsultation`; `needsDetails()`, `section()` | Implemented |
| `NoticeRecipient` | Enum: `Commission`, `Subject`, `Public`; `section()`, `rubrics()` | Implemented |
| `NoticeRubric` | Enum of the rubrics of the notices of sections 3 and 5 of the regulation; `paragraph($recipient)`, `fedBy($field)` | Implemented |
| `NoticeOrganisation` | Who gives a notice: `name`, `enterpriseNumber`, `contactName`, `contactDetails`, each nullable | Implemented |

### `Domain\Exception`

| Type | Role | State |
|---|---|---|
| `PrivacyError` | Marker interface (extends `Throwable`) on everything the bundle throws | Implemented |
| `InvalidPrivacyValue` | `InvalidArgumentException`: a value, declaration or schedule refused its construction; `because($reason)` | Implemented |

## Application

| Type | Role | State |
|---|---|---|
| `SubjectExport` | Assembles one sorted section per exporter; `export($subject, ?$lock)`, the lock renewed before each module | Implemented; wired over `phil_privacy.exporter` |
| `SubjectErasure` | Runs every eraser, isolates failures; `erase($subject, ?$lock)`, the lock renewed before each eraser | Implemented; wired over `phil_privacy.eraser` |
| `RetentionRun` | Checks every purge order has a purger, then purges; `orders($now)`, `run($now, ?$lock)`, `jobsWithoutCutoff()` | Implemented; wired with a schedule |
| `RetentionJob` | The run at the present instant, one at a time, its lock renewed before each purge order; `plan()`, `run()`, `LOCK`, `LOCK_TTL` | Implemented; wired with a schedule |
| `Lock\HeldLock` | A lock renewed between the steps of a work; `renew()`, `release()` | Implemented |
| `ConfiguredRetentionSchedule` | The `RetentionScheduleSource` of `retention.rules` | Implemented; wired |
| `AccountDeletion\AccountDeletions` | The stages of a deletion; `request()`, `cancel()`, `openFor()`, `dueIdentifiers()`, `advance()`, `purgeExpiredExports()` | Implemented; wired |
| `AccountDeletion\DeletionWorkflow` | The state machine on `symfony/workflow`; `create()`, `definition()` | Implemented; wired |
| `Consent\ConsentRecorder` | A dated choice on the current version, and its proof; `decide()` | Implemented; wired |
| `EmailTracking\EmailTrackingConsents` | Each member's consent to tracked emails; `opens()`, `clicks()`, `current()`, `decide()`, `version()`; exporter and eraser of the module `email_tracking` | Implemented; wired when enabled |
| `PersonalDataDownload` | A member's export as an archive, one at a time; `of($subject)` | Implemented; wired |
| `Register\ProcessingRegister`, `Register\RegisterEntry`, `Register\ProcessingActivity` | The register of processing; `activities()`, `entries()`, `gaps()` | Implemented; wired |
| `Export\ZipFile` | A ZIP archive written in memory; `add()`, `bytes()` | Implemented |
| `Export\ExportPackage` | The archive of an access answer; `zip($sections)` | Implemented; wired |
| `Export\PlainExportReadme` | The README in plain English | Implemented; wired without Twig |
| `GuestRequest\GuestRequests` | The requests without an account; `submit()`, `open()`, `access()`, `erase()`, `erasesAsAWorkflow()`, `startErasure()`; `WORKFLOW_RETENTION` | Implemented; wired when enabled |
| `Erasure\ErasureRequests` | The erasure workflow; `inventory()`, `open()`, `openForGuest()`, `find()`, `progress()`, `run()`, `acknowledge()`, `giveUp()`, `replay()`, `fail()`; `LEASE` | Implemented; wired when `erasure_workflow.enabled` |
| `GuestRequest\EmailGuestSubjects` | The verified address as the data subject | Implemented; wired when enabled |
| `PrivacyRights` | Which rights are on; the two sentences; `warnings()` | Implemented; wired |
| `Message\AdvanceAccountDeletion`, `Message\ProcessDueAccountDeletions`, `Message\RunRetention`, `Message\EraseModule` | Messages, identifiers only; `EraseModule::TYPE`, the type of its outbox record | Implemented |
| `Handler\AdvanceAccountDeletionHandler`, `Handler\ProcessDueAccountDeletionsHandler`, `Handler\RunRetentionHandler`, `Handler\EraseModuleHandler` | Their handlers | Implemented; wired |
| `Anonymization\AnonymizationRun` | Rewrites a plan's columns in one transaction; `run($plan, $target, ?$seed, ?$salt)` | Implemented; wired |
| `Anonymization\AnonymizationReport` | Rows rewritten by table; `total()` | Implemented |
| `Anonymization\ProductionGuard` | Refuses production environments and protected hosts and databases; `check($database)` | Implemented; wired |
| `Incident\IncidentJournalRecorder` | The journal of an incident (P-39.1, sections 3.5 and 3.7); `record($incidentId, $kind, $at, $party, $details, ?$until)`, `entries($incidentId)`, `isDeferred($entries)` | Implemented; wired |
| `Incident\IncidentNotices` | The drafts of the notices of an incident; `draft($incident, $recipient)`, `followUp($incident, $revisions, ?$since)`, `IDENTIFICATION`; sends nothing | Implemented; wired |
| `Incident\IncidentNoticeDraft` | A draft before it is written: `recipient`, `incident`, `organisation`, `rubrics`, `missing`, `since`; `isFollowUp()`, `shows()`, `lacks()`, `changes()` | Implemented |

### `Application\Contract`

| Type | Role | State |
|---|---|---|
| `PersonalDataExporter` | `module()`, `export($subject)` | Port |
| `PersonalDataEraser` | `module()`, `erase($subject)`; idempotent | Port |
| `ExternalPersonalDataEraser` | `module()`, `request($subject, $idempotencyKey)`; idempotent, acknowledged later | Port |
| `ErasureMessages` | `record($requestId, $module, $idempotencyKey)` | Port; `OutboxErasureMessages` |
| `ErasureRequestStore` | `open()`, `find()`, `steps()`, `step()`, `lease()`, `complete()`, `await()`, `acknowledge()`, `release()`, `flag()`, `reopen()`, `settle()` | Port; `DbalErasureRequests` |
| `RetentionScheduleSource` | `load()` | Port |
| `RetentionPurger` | `store()`, `purge($order)` | Port |
| `AnonymizationTarget` | `keyOf()`, `rows()`, `update()`, `transactional()` | Port; `DbalAnonymizationTarget` |
| `AccountDeletionStore` | `find()`, `openFor()`, `save()`, `dueIdentifiers()` | Port; `DbalAccountDeletions` |
| `ExportArchive` | `put()`, `get()`, `purgeExpired()` | Port; `EncryptedExportArchive` |
| `ConsentProofs` | `record()` | Port; `DbalConsentProofs`, `LoggedConsentProofs` |
| `CurrentDataSubject` | `current()` | Port; `SecurityCurrentDataSubject` |
| `DataSubjectUser` | `dataSubjectIdentifier()`, on the application's user class | Port |
| `ExportReadme` | `render($modules, $madeAt)` | Port; `TwigExportReadme`, `PlainExportReadme` |
| `GuestRequestStore` | `save()`, `find()`, `delete()`, `retain()`, `purgeExpired()` | Port; `DbalGuestRequests` |
| `GuestLinkSender` | `send($request, $token)` | Port; `MailerGuestLinkSender` |
| `GuestSubjects` | `subjectOf($email)` | Port; `EmailGuestSubjects` |
| `IncidentRegister` | `save()`, `find()`, `all()` | Port; `DbalIncidentRegister` |
| `IncidentHistory` | `revisions()` | Port; `DbalIncidentRegister` |
| `IncidentJournal` | `append()`, `journal()` | Port; `DbalIncidentRegister` |
| `NoticeOrganisationSource` | `organisation()` | Port; `SiteIdentityOrganisation` |
| `EmailTrackingStore` | `find()`, `save()`, `forget()`, `store()` | Port; `DbalEmailTrackingConsents` |

### `Application\Exception`

| Type | Role | State |
|---|---|---|
| `ErasureIncomplete` | `RuntimeException`; `$completed` reports, `$failures` by module, none chained as the previous exception; `after($completed, $failures)` | Implemented |
| `AccountErasureIncomplete` | `RuntimeException`, thrown to Messenger by `AdvanceAccountDeletionHandler`; `$deletionId`, `$modules`, no previous exception; `of($deletionId, $erasure)` | Implemented |
| `MisconfiguredPrivacy` | `LogicException`; `duplicateModule()`, `duplicatePurger()`, `missingPurger()`, `userWithoutSubject()`, `erasureLease()` | Implemented |
| `ModuleErasureFailed` | `RuntimeException`, thrown to Messenger by `EraseModuleHandler`; names the request and the module, no previous exception; `of($requestId, $module)` | Implemented |
| `AccountDeletionBusy` | `RuntimeException`; another process holds the lock of this person | Implemented |
| `LockLost` | `RuntimeException`; the lock of a work in steps expired, was taken, or could not be confirmed; `create()` | Implemented |
| `AnonymizationRefused` | `RuntimeException`; `inEnvironment()`, `protectedDatabase()`, `withoutKey()`, `keyColumn()` | Implemented |
| `TooManyGuestRequests` | `RuntimeException`; a client over its limit; `retryIn()`, `retryAfter()` | Implemented |
| `NoticeRefused` | `RuntimeException`; no draft written: `publicWithoutReason()`, `followUpWithoutNotice()`, `followUpWithoutHistory()`, `invalidSince()` | Implemented |
| `JournalRefused` | `RuntimeException`; nothing added to the journal of an incident: `withoutJournal()`, `unknownIncident()`, `beforeDiscovery()`, `nothingDeferred()` | Implemented |

## Infrastructure

| Type | Role | State |
|---|---|---|
| `Infrastructure\Monolog\RedactingProcessor` | Monolog processor over a `ContextRedactor`; `fromConfiguration()`, `__invoke(LogRecord)` | Implemented; wired and tagged `monolog.processor` |
| `Infrastructure\Console\ExportSubjectCommand` | `phil:privacy:export`: the answer to an access request, printed or written to a `0600` file (`--output`, `--force`) | Implemented; wired with Symfony Console |
| `Infrastructure\Console\EraseSubjectCommand` | `phil:privacy:erase`: every module's erasure, with `--confirm` | Implemented; wired with Symfony Console |
| `Infrastructure\Console\ProcessingRegisterCommand` | `phil:privacy:register`: the register of processing, Markdown or JSON, `--strict` | Implemented; wired with Symfony Console |
| `Infrastructure\Console\IncidentCommand`, `IncidentsCommand` | `phil:privacy:incident`, `phil:privacy:incidents` | Implemented; wired with Symfony Console |
| `Infrastructure\Console\IncidentJournalCommand` | `phil:privacy:incident-journal`: adds an entry to the journal of an incident, or prints it | Implemented; wired with Symfony Console |
| `Infrastructure\Console\IncidentNoticeCommand` | `phil:privacy:incident-notice`: a draft of a notice of an incident in Markdown, to the standard output or a new file; sends and records nothing | Implemented; wired with Symfony Console, Twig and the translator |
| `Infrastructure\Twig\TwigIncidentNotice` | Renders a draft from `@PhilPrivacy/incident_notice/`, `LOCALES` | Implemented |
| `Infrastructure\LegalNotices\SiteIdentityOrganisation` | The `NoticeOrganisationSource` of the identity of the site of phil/legal-notices | Implemented; wired when the identity is a service |
| `Infrastructure\Console\AnonymizeDatabaseCommand` | `phil:privacy:anonymize` | Implemented; wired with Console and DBAL |
| `Infrastructure\Database\ConnectionIdentity` | `of($connection)`: the `DatabaseIdentity` of a DBAL connection, without connecting | Implemented |
| `Infrastructure\Database\DbalAnonymizationTarget` | The `AnonymizationTarget` of a DBAL connection, in pages by key | Implemented |
| `Infrastructure\Doctrine\AnonymizationPlanReader` | `plan()`: from the ORM mapping, the categories and the configured tables | Implemented |
| `Infrastructure\DependencyInjection\AnonymizationServices` | Declares the `database` and `anonymization` services | Implemented |
| `Infrastructure\DependencyInjection\DatabaseServicesPass` | Removes the database services when the connection is not a service | Implemented |
| `Infrastructure\DependencyInjection\RightsConfiguration`, `RightsServices` | The configuration and the services of the rights, the consent and the retention job | Implemented |
| `Infrastructure\DependencyInjection\CookieServices` | The consent policy and the cookie inventory, read at the build | Implemented |
| `Infrastructure\DependencyInjection\GuestRequestServices` | The requests without an account and their limits | Implemented |
| `Infrastructure\DependencyInjection\PrivacyServicesPass` | Removes what the application cannot run | Implemented |
| `Infrastructure\Console\RunRetentionCommand`, `ProcessDeletionsCommand` | `phil:privacy:retention`, `phil:privacy:deletions` | Implemented; wired with Symfony Console |
| `Infrastructure\Console\DisabledRightsWarning` | The warning before every command when a right is off | Implemented |
| `Infrastructure\Database\PrivacyTables`, `DbalAccountDeletions`, `DbalConsentProofs` | The tables, their keys in `utf8mb4_bin` on MySQL and MariaDB when the platform is given; the store, the proofs; the last two also purge | Implemented |
| `Infrastructure\Database\DbalGuestRequests` | The requests without an account, the address sealed | Implemented |
| `Infrastructure\Database\DbalIncidentRegister` | The register of incidents and its corrections; also purges, never below five years from the discovery | Implemented |
| `Infrastructure\Database\DbalErasureRequests` | The erasure requests and their steps; also purges the closed requests | Implemented; wired when enabled |
| `Infrastructure\Outbox\OutboxErasureMessages`, `EraseModuleCodec`, `EraseModuleRelay` | The messages as outbox records of type `phil_privacy.erase_module`, the outbox codec of `EraseModule`, which skips the record of a request that is gone, and the hand-over of the message to the bus | Implemented; wired when enabled |
| `Infrastructure\Messenger\ErasureFailureListener` | Marks a step as needing attention once Messenger gives up on its message | Implemented; wired when enabled |
| `Infrastructure\Console\ErasureRequestCommand` | `phil:privacy:erasure`: a request module by module, `--replay`, `--fail --confirm` | Implemented; wired when enabled |
| `Infrastructure\Http\ErasureProgressController` | The progress page and JSON, for the person, an operator, or the holder of the secret | Implemented; wired |
| `Infrastructure\DependencyInjection\ErasureWorkflowServices` | The services of the erasure workflow; `EXTERNAL_ERASER_TAG`, `OPERATOR_ROLE` | Implemented |
| `Infrastructure\Database\DbalEmailTrackingConsents` | The members' choices about tracked emails, one row each | Implemented |
| `Infrastructure\Database\UtcMoments` | The instants of the tables, in UTC and the platform's format only; `text()`, `read()` | Implemented |
| `Infrastructure\Mailer\MailerGuestLinkSender` | The link sent at once through the mailer's transports | Implemented; wired when enabled |
| `Infrastructure\Routing\GuestLinkOrigin` | The link generated on `public_origin`, and the boot refused in `prod` without it or `framework.trusted_hosts` | Implemented; wired with the mailer sender |
| `Infrastructure\Doctrine\PrivacySchemaListener` | Adds the tables to the generated schema | Implemented |
| `Infrastructure\Filesystem\EncryptedExportArchive` | Sealed files, private, removed at expiry | Implemented |
| `Infrastructure\Log\LoggedConsentProofs` | The proofs as log lines | Implemented |
| `Infrastructure\Security\SecurityCurrentDataSubject` | The signed-in user, through `DataSubjectUser` | Implemented |
| `Infrastructure\Http\ConsentController`, `PreferencesController`, `PrivacyPages`, `ConsentCookie`, `ConsentContext`, `ConsentVaryListener` | The pages, the cookie, the state of a visitor and the `Vary` header | Implemented; wired |
| `Infrastructure\Http\GuestRequestController` | The pages of the requests without an account | Implemented; wired |
| `Infrastructure\Http\CookieInventoryFactory`, `UndeclaredCookies`, `UndeclaredCookieListener` | The inventory with the consent cookie, the undeclared cookies of a response, the warning in development | Implemented; wired |
| `Infrastructure\Routing\PrivacyRoutes`, `MountingRouteLoader` | The routes, and the mount in front of the application's | Implemented; wired |
| `Infrastructure\Twig\ConsentExtension` | `privacy_consent()`, `privacy_consent_pending()`, `privacy_consent_banner()`, `privacy_cookie_policy()` | Implemented; wired |
| `Infrastructure\Twig\EmbedExtension` | `privacy_embed()`: the frame's HTTPS address checked, a YouTube or Vimeo video turned into its player without cookies by `CookielessVideo` of `phil/url`; `MAX_URL_LENGTH` | Implemented; wired |
| `Infrastructure\Twig\TwigExportReadme` | The README of an archive from its template | Implemented; wired |
| `Infrastructure\Testing\AssertsDeclaredCookies` | `assertResponseSetsDeclaredCookiesOnly()`, for a functional test | Implemented |
| `Infrastructure\Scheduler\PrivacySchedule` | The schedule `phil_privacy` | Implemented; wired with `symfony/scheduler` |

## Not in the bundle

No Doctrine mapping and no discovery of `#[PersonalData]` classes outside Doctrine entities and `register.classes`.
Backing a database up is the work of `phil/backup-bundle`, and the expiry of a published content that of
`phil/content-expiry`.
