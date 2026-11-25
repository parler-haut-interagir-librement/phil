<!-- phil-dev source-sha256=087e86af219cf07b6a5916d63c1a01b4be4e1c09059fa8470d368a0f800e9a97 -->
# Référence de l'API

Chaque type sous `Phil\PrivacyBundle\`, par couche. « Implémenté » veut dire implémenté et couvert par des tests
unitaires.

## Bundle

| Type | Rôle | État |
|---|---|---|
| `PhilPrivacyBundle` | `AbstractBundle`, alias `phil_privacy` ; enregistre `RedactingProcessor`, autoconfigure les ports d'exportateur et d'effaceur, enregistre `SubjectExport`, `SubjectErasure`, `ProcessingRegister` et leurs trois commandes, les services d'anonymisation par `AnonymizationServices`, et les droits, le consentement, l'inventaire des témoins, les demandes sans compte, les incidents et la tâche de conservation par `RightsServices` ; `ANONYMIZER_TAG`, `LOCALE_PACK_TAG`, `DATABASE_IDENTITY` | Implémenté |

## Domaine

### `Domain\Attribute`

| Type | Rôle | État |
|---|---|---|
| `PersonalData` | Attribut de propriété : `$category` (`DataCategory`), `$retention` (`?string`, un identifiant de règle) | Implémenté |
| `Anonymize` | Attribut de propriété : `$type`, `$options` (`AnonymizerOptions`) ; `TYPE_PATTERN` | Implémenté |
| `AsAnonymizer` | Attribut de classe qui enregistre un `Anonymizer` de l'application sous `$type` | Implémenté |

### `Domain\Declaration`

| Type | Rôle | État |
|---|---|---|
| `DeclaredPersonalFields` | `of($class)` et `uncoveredIn($class, ?$schedule)` : lit les déclarations, les parents d'abord | Implémenté |
| `PersonalField` | Une propriété déclarée : `$class`, `$property`, `$category`, `$retention` ; `isCoveredByRetention()`, `isCoveredBy($schedule)` | Implémenté |
| `DeclaredAnonymizations` | `of($class)` : les déclarations `#[Anonymize]` par propriété, les parents d'abord | Implémenté |
| `AnonymizedField` | Une propriété déclarée : `$class`, `$property`, `$anonymize` | Implémenté |
| `Lineage` | `properties($class)` : les propriétés d'une classe et de ses parents, chacune sur la classe qui la déclare | Implémenté |

### `Domain\Anonymization`

| Type | Rôle | État |
|---|---|---|
| `AnonymizationContext` | `$options`, `$locale`, `$random`, `$salt` ; `pick()`, `fill($pattern)`, `digest($value)` | Implémenté |
| `AnonymizerOptions` | Des options scalaires nommées ; `has()`, `string()`, `int()`, `scalar()` | Implémenté |
| `Anonymizers` | Les anonymiseurs par type ; `builtIn()`, `with()`, `get()`, `types()` | Implémenté |
| `Anonymizer\EmailAnonymizer`, `PersonNameAnonymizer`, `PhoneAnonymizer`, `AddressAnonymizer`, `IbanAnonymizer`, `DateShiftAnonymizer`, `ConstantAnonymizer`, `NullAnonymizer`, `HashAnonymizer`, `LoremAnonymizer`, `PatternAnonymizer` | Les treize types intégrés | Implémenté |
| `NamePart`, `AddressPart` | Énumérations : la partie d'un nom, l'option `part` de `address` | Implémenté |
| `ColumnRule` | Une colonne : `$table`, `$column`, `$type`, `$options`, `$origin` | Implémenté |
| `AnonymizationPlan` | Les règles par table, une règle plus tardive remplaçant une plus ancienne ; `tables()`, `isEmpty()`, `$uncovered` | Implémenté |
| `DatabaseIdentity` | `$platform`, `$host`, `$name` ; `describe()` | Implémenté |
| `Locale\LocalePack` | Noms, rues, villes, régions, codes postaux, plages téléphoniques fictives, pays, format d'adresse ; `CODE_PATTERN` | Implémenté |
| `Locale\LocalePacks` | Les paquets par code ; `builtIn()`, `with()`, `get()`, `codes()` | Implémenté |
| `Locale\BuiltInLocales` | `all()` : `fr_CA`, `en_CA`, `fr_FR`, `en_US`, `en_GB`, `en_AU`, `de_DE`, `es_ES` | Implémenté |

### `Domain\Contract`

| Type | Rôle | État |
|---|---|---|
| `Anonymizer` | `anonymize($value, $context)` : une valeur non nulle changée en une qui ne dit rien | Port ; les anonymiseurs intégrés de `Domain\Anonymization\Anonymizer` |

### `Domain\Subject`

| Type | Rôle | État |
|---|---|---|
| `DataSubject` | Identifiant opaque d'une personne ; `fromString()`, `equals()`, `Stringable` ; `MAX_LENGTH = 255` | Implémenté |
| `DataCategory` | Classification `snake_case` en minuscules ; `fromString()`, `equals()` ; `MAX_LENGTH = 64` | Implémenté |
| `ModuleName` | Le nom sous lequel un module répond aux demandes d'une personne ; `fromString()` | Implémenté |
| `ErasureReport` | Les effets d'un module par stockage ; `of($module, $effects)` ; jamais vide | Implémenté |

### `Domain\Redaction`

| Type | Rôle | État |
|---|---|---|
| `Denylist` | Clés exactes normalisées et motifs `*` ; `of()`, `fromList()`, `denies()`, `keys()` | Implémenté |
| `UrlRedactor` | Chaînes de requête, informations d'utilisateur des adresses, segments de chemin à jeton déclarés ; `redact()` ; `PLACEHOLDER = '[redacted]'` | Implémenté |
| `CommandLineRedactor` | Les valeurs des options longues refusées et des options courtes déclarées ; `redact()` | Implémenté |
| `ContextRedactor` | Parcours du message et du contexte qui combine les trois ; `redactMessage()`, `redactContext()` ; `PLACEHOLDER` | Implémenté |

### `Domain\Retention`

| Type | Rôle | État |
|---|---|---|
| `RetentionPeriod` | `fixed()`, `untilEvent()`, `indefinite()`, `notYetFixed()` ; `isFixed()`, `cutoff($now)` en UTC avec la fin de mois ramenée, `lastsAtLeast($minimum)` | Implémenté |
| `RetentionPeriodKind` | Énumération : `Fixed`, `UntilEvent`, `Indefinite`, `NotYetFixed` | Implémenté |
| `RetentionRule` | Une ligne du calendrier ; refuse une tâche sur une durée indéfinie et `AwaitsExpiry` sans durée fixe ; `ID_PATTERN` ; `holdsPersonalInformation()` ; `$processing` | Implémenté |
| `Processing` | Ce que dit le registre d'un stockage : `$purpose`, `$recipients`, `$processors`, `$location` ; `isComplete()` | Implémenté |
| `Processor` | Un fournisseur de services et l'endroit où il garde les enregistrements ; `toString()` | Implémenté |
| `RetentionSchedule` | Les lignes par identifiant ; `has()`, `rule()`, `rules()`, `purgeOrders($now)`, `jobsWithoutCutoff()` | Implémenté |
| `PurgeOrder` | `$ruleId`, `$store`, `$cutoff` | Implémenté |
| `Mechanism` | Énumération : `Job`, `Ttl`, `Rotation`, `Event`, `Manual` | Implémenté |
| `ErasureEffect` | Énumération : `Deleted`, `Anonymised` (un squelette jugé non identifiable), `Pseudonymised` (encore des renseignements personnels pour qui détient le lien de retour), `Redacted`, `AwaitsExpiry`, `Survives` ; `leavesPersonalData()`, faux pour `Deleted` et `Anonymised` seulement | Implémenté |

### `Domain\Erasure`

| Type | Rôle | État |
|---|---|---|
| `ErasureInventory` | Les modules dont les effaceurs sont déclarés, et si chacun est externe ; `of($local, $external)`, `has()`, `isExternal()`, `select($modules)` ; `$version`, une empreinte de 16 chiffres hexadécimaux ; `VERSION_PATTERN` | Implémenté |
| `ErasureRequest` | Une demande d'effacement : une personne ou la demande sans compte dont elle provient, la version de l'inventaire, l'état ; `forSubject()`, `forGuest()`, `isAbout()`, `isOpenedBy($token)` ; `ID_PATTERN`, `TOKEN_PATTERN` | Implémenté |
| `ErasureStep` | Un module d'une demande ; `pending()`, `keyOf($request, $module)`, `idempotencyKey()` | Implémenté |
| `ErasureProgress` | Une demande et ses étapes, telles que les donnent la page et le JSON ; `counts()`, `toArray()` | Implémenté |
| `RequestState` | Énumération : `Running`, `NeedsAttention`, `Completed`, `Failed` ; `isClosed()` | Implémenté |
| `StepState` | Énumération : `Pending`, `Running`, `Completed`, `NeedsAttention` | Implémenté |
| `AttentionReason` | Énumération : `RetriesExhausted`, `UnknownModule`, `SubjectUnavailable` | Implémenté |
| `AccessResponse` | Énumération : `Returned`, `AsCategory`, `NotReturned` | Implémenté |

### `Domain\AccountDeletion`

| Type | Rôle | État |
|---|---|---|
| `AccountDeletion` | Une demande ; `isOpen()`, `isDue()`, `isCancellable()` | Implémenté |
| `DeletionPlace` | `requested`, `exported`, `erased`, `cancelled` | Implémenté |

### `Domain\Consent`

| Type | Rôle | État |
|---|---|---|
| `ConsentCategory` | Une catégorie déclarée ; `label()`, `description()` | Implémenté |
| `ConsentPolicy` | Les catégories et la version du texte ; `optional()`, `isNecessary()`, `retain()` | Implémenté |
| `ConsentChoice` | Un choix daté sur une version ; `grants()` | Implémenté |
| `ConsentState` | Ce qui est permis pour un visiteur ; `isGranted()`, `isPending()`, `granted()` | Implémenté |
| `ConsentToken` | La valeur signée du témoin ; `encode()`, `decode()` | Implémenté |

### `Domain\EmailTracking`

| Type | Rôle | État |
|---|---|---|
| `EmailTrackingChoice` | Le choix daté d'un membre sur les courriels suivis, sur une version du texte ; `allowsOpens()`, `allowsClicks()`, `toArray()` | Implémenté |

### `Domain\Cookie`

| Type | Rôle | État |
|---|---|---|
| `DeclaredCookie` | Une ligne de l'inventaire ; `matches()`, `isFamily()`, `isSession()`, `purpose($locale)` | Implémenté |
| `CookieStorage` | Énumération : `Cookie`, `LocalStorage`, `SessionStorage` | Implémenté |
| `CookieInventory` | Les témoins déclarés ; `fromArray()`, `with()`, `declares()`, `undeclared()`, `ofCategory()`, `namesOf()`, `groups()` | Implémenté |
| `CookieGroup` | Les témoins d'une catégorie, ou du consentement lui-même | Implémenté |

### `Domain\GuestRequest`

| Type | Rôle | État |
|---|---|---|
| `GuestRequest` | Une demande sans compte ; `issue()`, `hash()`, `isExpired()`, `isOpenedBy()` | Implémenté |
| `GuestRequestKind` | Énumération : `Access`, `Erasure` | Implémenté |

### `Domain\Incident`

| Type | Rôle | État |
|---|---|---|
| `Incident` | Une ligne du registre des incidents, avec les huit paragraphes de l'article 7 du règlement québécois (r. 3.1) ; `record()`, `update()` avec `clear`, `isClosed()`, `pendingNotifications()`, `missingParagraphs()`, `knownSince()`, `MINIMUM_RETENTION`, `CLEARABLE` | Implémenté |
| `IncidentRevision` | Ce que contenait une ligne avant une correction : le champ, sa valeur précédente, le numéro et le moment de la correction | Implémenté |
| `PublicNoticeReason` | Énumération : `IncreasedInjury`, `ExcessiveHardship`, `NoContactDetails`, `PromptAction` ; `replacesIndividualNotice()` | Implémenté |
| `JournalEntry` | Une entrée du journal d'un incident : son numéro, son genre, quand elle a été faite, par qui, quoi et pourquoi, la fin prévue d'un report, quand elle a été écrite ; `numbered()` | Implémentée |
| `JournalKind` | Énumération : `ThirdPartyCommunication`, `NoticeDeferral`, `DeferralLifted`, `OfficerConsultation` ; `needsDetails()`, `section()` | Implémentée |
| `NoticeRecipient` | Énumération : `Commission`, `Subject`, `Public` ; `section()`, `rubrics()` | Implémenté |
| `NoticeRubric` | Énumération des rubriques des avis des articles 3 et 5 du règlement ; `paragraph($recipient)`, `fedBy($field)` | Implémenté |
| `NoticeOrganisation` | Qui donne un avis : `name`, `enterpriseNumber`, `contactName`, `contactDetails`, chacun nullable | Implémenté |

### `Domain\Secret`

| Type | Rôle | État |
|---|---|---|
| `KeyDerivation` | Les clés du bundle, chacune le HKDF-SHA-256 du secret de l'application sous l'étiquette de son usage ; `key($secret, $usage)`, refuse un secret vide ; `LENGTH`, une constante par étiquette | Implémenté |

### `Domain\Exception`

| Type | Rôle | État |
|---|---|---|
| `PrivacyError` | Interface marqueur (étend `Throwable`) sur tout ce que lève le bundle | Implémenté |
| `InvalidPrivacyValue` | `InvalidArgumentException` : une valeur, une déclaration ou un calendrier a refusé sa construction ; `because($reason)` | Implémenté |

## Application

| Type | Rôle | État |
|---|---|---|
| `SubjectExport` | Assemble une section triée par exportateur ; `export($subject, ?$lock)`, le verrou renouvelé avant chaque module | Implémenté ; branché sur `phil_privacy.exporter` |
| `SubjectErasure` | Exécute chaque effaceur, isole les échecs ; `erase($subject, ?$lock)`, le verrou renouvelé avant chaque effaceur | Implémenté ; branché sur `phil_privacy.eraser` |
| `RetentionRun` | Vérifie que chaque ordre de purge a un purgeur, puis purge ; `orders($now)`, `run($now, ?$lock)`, `jobsWithoutCutoff()` | Implémenté ; branché avec un calendrier |
| `RetentionJob` | L'exécution à l'instant présent, une à la fois, son verrou renouvelé avant chaque ordre de purge ; `plan()`, `run()`, `LOCK`, `LOCK_TTL` | Implémenté ; branché avec un calendrier |
| `Lock\HeldLock` | Un verrou renouvelé entre les étapes d'un travail ; `renew()`, `release()` | Implémenté |
| `ConfiguredRetentionSchedule` | La `RetentionScheduleSource` de `retention.rules` | Implémenté ; branché |
| `AccountDeletion\AccountDeletions` | Les étapes d'une suppression ; `request()`, `cancel()`, `openFor()`, `dueIdentifiers()`, `advance()`, `purgeExpiredExports()` | Implémenté ; branché |
| `AccountDeletion\DeletionWorkflow` | La machine à états sur `symfony/workflow` ; `create()`, `definition()` | Implémenté ; branché |
| `Consent\ConsentRecorder` | Un choix daté sur la version en vigueur, et sa preuve ; `decide()` | Implémenté ; branché |
| `EmailTracking\EmailTrackingConsents` | Le consentement de chaque membre aux courriels suivis ; `opens()`, `clicks()`, `current()`, `decide()`, `version()` ; exportateur et effaceur du module `email_tracking` | Implémenté ; branché une fois activé |
| `PersonalDataDownload` | L'export d'un membre en archive, un à la fois ; `of($subject)` | Implémenté ; branché |
| `Register\ProcessingRegister`, `Register\RegisterEntry`, `Register\ProcessingActivity` | Le registre des traitements ; `activities()`, `entries()`, `gaps()` | Implémenté ; branché |
| `Export\ZipFile` | Une archive ZIP écrite en mémoire ; `add()`, `bytes()` | Implémenté |
| `Export\ExportPackage` | L'archive d'une réponse d'accès ; `zip($sections)` | Implémenté ; branché |
| `Export\PlainExportReadme` | Le README en anglais simple | Implémenté ; branché sans Twig |
| `GuestRequest\GuestRequests` | Les demandes sans compte ; `submit()`, `open()`, `access()`, `erase()`, `erasesAsAWorkflow()`, `startErasure()` ; `WORKFLOW_RETENTION` | Implémenté ; branché une fois activé |
| `Erasure\ErasureRequests` | Le workflow d'effacement ; `inventory()`, `open()`, `openForGuest()`, `find()`, `progress()`, `run()`, `acknowledge()`, `giveUp()`, `replay()`, `fail()` ; `LEASE` | Implémenté ; branché quand `erasure_workflow.enabled` |
| `GuestRequest\EmailGuestSubjects` | L'adresse vérifiée comme personne concernée | Implémenté ; branché une fois activé |
| `PrivacyRights` | Quels droits sont actifs ; les deux phrases ; `warnings()` | Implémenté ; branché |
| `Message\AdvanceAccountDeletion`, `Message\ProcessDueAccountDeletions`, `Message\RunRetention`, `Message\EraseModule` | Des messages, identifiants seulement ; `EraseModule::TYPE`, le type de son enregistrement d'outbox | Implémenté |
| `Handler\AdvanceAccountDeletionHandler`, `Handler\ProcessDueAccountDeletionsHandler`, `Handler\RunRetentionHandler`, `Handler\EraseModuleHandler` | Leurs gestionnaires | Implémenté ; branché |
| `Anonymization\AnonymizationRun` | Réécrit les colonnes d'un plan en une transaction ; `run($plan, $target, ?$seed, ?$salt)` | Implémenté ; branché |
| `Anonymization\AnonymizationReport` | Les lignes réécrites par table ; `total()` | Implémenté |
| `Anonymization\ProductionGuard` | Refuse les environnements de production et les hôtes et bases protégés ; `check($database)` | Implémenté ; branché |
| `Incident\IncidentJournalRecorder` | Le journal d'un incident (P-39.1, articles 3.5 et 3.7) ; `record($incidentId, $kind, $at, $party, $details, ?$until)`, `entries($incidentId)`, `isDeferred($entries)` | Implémenté ; branché |
| `Incident\IncidentNotices` | Les brouillons des avis d'un incident ; `draft($incident, $recipient)`, `followUp($incident, $revisions, ?$since)`, `IDENTIFICATION` ; n'envoie rien | Implémenté ; branché |
| `Incident\IncidentNoticeDraft` | Un brouillon avant d'être écrit : `recipient`, `incident`, `organisation`, `rubrics`, `missing`, `since` ; `isFollowUp()`, `shows()`, `lacks()`, `changes()` | Implémenté |

### `Application\Contract`

| Type | Rôle | État |
|---|---|---|
| `PersonalDataExporter` | `module()`, `export($subject)` | Port |
| `PersonalDataEraser` | `module()`, `erase($subject)` ; idempotent | Port |
| `ExternalPersonalDataEraser` | `module()`, `request($subject, $idempotencyKey)` ; idempotent, accusé de réception plus tard | Port |
| `ErasureMessages` | `record($requestId, $module, $idempotencyKey)` | Port ; `OutboxErasureMessages` |
| `ErasureRequestStore` | `open()`, `find()`, `steps()`, `step()`, `lease()`, `complete()`, `await()`, `acknowledge()`, `release()`, `flag()`, `reopen()`, `settle()` | Port ; `DbalErasureRequests` |
| `RetentionScheduleSource` | `load()` | Port |
| `RetentionPurger` | `store()`, `purge($order)` | Port |
| `AnonymizationTarget` | `keyOf()`, `rows()`, `update()`, `transactional()` | Port ; `DbalAnonymizationTarget` |
| `AccountDeletionStore` | `find()`, `openFor()`, `save()`, `dueIdentifiers()` | Port ; `DbalAccountDeletions` |
| `ExportArchive` | `put()`, `get()`, `purgeExpired()` | Port ; `EncryptedExportArchive` |
| `ConsentProofs` | `record()` | Port ; `DbalConsentProofs`, `LoggedConsentProofs` |
| `CurrentDataSubject` | `current()` | Port ; `SecurityCurrentDataSubject` |
| `DataSubjectUser` | `dataSubjectIdentifier()`, sur la classe de l'utilisateur de l'application | Port |
| `ExportReadme` | `render($modules, $madeAt)` | Port ; `TwigExportReadme`, `PlainExportReadme` |
| `GuestRequestStore` | `save()`, `find()`, `delete()`, `retain()`, `purgeExpired()` | Port ; `DbalGuestRequests` |
| `GuestLinkSender` | `send($request, $token)` | Port ; `MailerGuestLinkSender` |
| `GuestSubjects` | `subjectOf($email)` | Port ; `EmailGuestSubjects` |
| `IncidentRegister` | `save()`, `find()`, `all()` | Port ; `DbalIncidentRegister` |
| `IncidentHistory` | `revisions()` | Port ; `DbalIncidentRegister` |
| `IncidentJournal` | `append()`, `journal()` | Port ; `DbalIncidentRegister` |
| `NoticeOrganisationSource` | `organisation()` | Port ; `SiteIdentityOrganisation` |
| `EmailTrackingStore` | `find()`, `save()`, `forget()`, `store()` | Port ; `DbalEmailTrackingConsents` |

### `Application\Exception`

| Type | Rôle | État |
|---|---|---|
| `ErasureIncomplete` | `RuntimeException` ; les rapports `$completed`, les `$failures` par module, aucune enchaînée comme exception précédente ; `after($completed, $failures)` | Implémenté |
| `AccountErasureIncomplete` | `RuntimeException`, lancée à Messenger par `AdvanceAccountDeletionHandler` ; `$deletionId`, `$modules`, aucune exception précédente ; `of($deletionId, $erasure)` | Implémenté |
| `MisconfiguredPrivacy` | `LogicException` ; `duplicateModule()`, `duplicatePurger()`, `missingPurger()`, `userWithoutSubject()`, `erasureLease()` | Implémenté |
| `ModuleErasureFailed` | `RuntimeException`, lancée vers Messenger par `EraseModuleHandler` ; nomme la demande et le module, sans exception précédente ; `of($requestId, $module)` | Implémenté |
| `AccountDeletionBusy` | `RuntimeException` ; un autre processus détient le verrou de cette personne | Implémenté |
| `LockLost` | `RuntimeException` ; le verrou d'un travail par étapes a expiré, a été pris, ou n'a pas pu être confirmé ; `create()` | Implémenté |
| `AnonymizationRefused` | `RuntimeException` ; `inEnvironment()`, `protectedDatabase()`, `withoutKey()`, `keyColumn()` | Implémenté |
| `TooManyGuestRequests` | `RuntimeException` ; un client au-delà de sa limite ; `retryIn()`, `retryAfter()` | Implémenté |
| `NoticeRefused` | `RuntimeException` ; aucun brouillon écrit : `publicWithoutReason()`, `followUpWithoutNotice()`, `followUpWithoutHistory()`, `invalidSince()` | Implémenté |
| `JournalRefused` | `RuntimeException` ; rien n'est ajouté au journal d'un incident : `withoutJournal()`, `unknownIncident()`, `beforeDiscovery()`, `nothingDeferred()` | Implémenté |

## Infrastructure

| Type | Rôle | État |
|---|---|---|
| `Infrastructure\Monolog\RedactingProcessor` | Processeur Monolog sur un `ContextRedactor` ; `fromConfiguration()`, `__invoke(LogRecord)` | Implémenté ; branché et étiqueté `monolog.processor` |
| `Infrastructure\Console\ExportSubjectCommand` | `phil:privacy:export` : la réponse à une demande d'accès, affichée ou écrite dans un fichier `0600` (`--output`, `--force`) | Implémenté ; branché avec Symfony Console |
| `Infrastructure\Console\EraseSubjectCommand` | `phil:privacy:erase` : l'effacement de chaque module, avec `--confirm` | Implémenté ; branché avec Symfony Console |
| `Infrastructure\Console\ProcessingRegisterCommand` | `phil:privacy:register` : le registre des traitements, en Markdown ou en JSON, `--strict` | Implémenté ; branché avec Symfony Console |
| `Infrastructure\Console\IncidentCommand`, `IncidentsCommand` | `phil:privacy:incident`, `phil:privacy:incidents` | Implémenté ; branché avec Symfony Console |
| `Infrastructure\Console\IncidentJournalCommand` | `phil:privacy:incident-journal` : ajoute une entrée au journal d'un incident, ou l'affiche | Implémenté ; branché avec Symfony Console |
| `Infrastructure\Console\IncidentNoticeCommand` | `phil:privacy:incident-notice` : un brouillon d'avis d'un incident en Markdown, vers la sortie standard ou un nouveau fichier ; n'envoie ni n'inscrit rien | Implémenté ; branché avec Symfony Console, Twig et le traducteur |
| `Infrastructure\Twig\TwigIncidentNotice` | Produit le rendu d'un brouillon à partir de `@PhilPrivacy/incident_notice/`, `LOCALES` | Implémenté |
| `Infrastructure\LegalNotices\SiteIdentityOrganisation` | La `NoticeOrganisationSource` tirée de l'identité du site de phil/legal-notices | Implémenté ; branché quand l'identité est un service |
| `Infrastructure\Console\AnonymizeDatabaseCommand` | `phil:privacy:anonymize` | Implémenté ; branché avec Console et DBAL |
| `Infrastructure\Database\ConnectionIdentity` | `of($connection)` : la `DatabaseIdentity` d'une connexion DBAL, sans se connecter | Implémenté |
| `Infrastructure\Database\DbalAnonymizationTarget` | L'`AnonymizationTarget` d'une connexion DBAL, par pages et par clé | Implémenté |
| `Infrastructure\Doctrine\AnonymizationPlanReader` | `plan()` : à partir du mappage ORM, des catégories et des tables configurées | Implémenté |
| `Infrastructure\DependencyInjection\AnonymizationServices` | Déclare les services `database` et `anonymization` | Implémenté |
| `Infrastructure\DependencyInjection\DatabaseServicesPass` | Retire les services de base de données quand la connexion n'est pas un service | Implémenté |
| `Infrastructure\DependencyInjection\RightsConfiguration`, `RightsServices` | La configuration et les services des droits, du consentement et de la tâche de conservation | Implémenté |
| `Infrastructure\DependencyInjection\CookieServices` | La politique de consentement et l'inventaire des témoins, lus à la construction | Implémenté |
| `Infrastructure\DependencyInjection\GuestRequestServices` | Les demandes sans compte et leurs limites | Implémenté |
| `Infrastructure\DependencyInjection\PrivacyServicesPass` | Retire ce que l'application ne peut pas faire tourner | Implémenté |
| `Infrastructure\Console\RunRetentionCommand`, `ProcessDeletionsCommand` | `phil:privacy:retention`, `phil:privacy:deletions` | Implémenté ; branché avec Symfony Console |
| `Infrastructure\Console\DisabledRightsWarning` | L'avertissement avant chaque commande quand un droit est désactivé | Implémenté |
| `Infrastructure\Database\PrivacyTables`, `DbalAccountDeletions`, `DbalConsentProofs` | Les tables, leurs clés en `utf8mb4_bin` sur MySQL et MariaDB quand la plateforme est donnée ; le stockage, les preuves ; les deux derniers purgent aussi | Implémenté |
| `Infrastructure\Database\DbalGuestRequests` | Les demandes sans compte, l'adresse scellée | Implémenté |
| `Infrastructure\Database\DbalIncidentRegister` | Le registre des incidents et ses corrections ; purge aussi, jamais sous cinq ans à compter de la découverte | Implémenté |
| `Infrastructure\Database\DbalErasureRequests` | Les demandes d'effacement et leurs étapes ; purge aussi les demandes fermées | Implémenté ; branché une fois activé |
| `Infrastructure\Outbox\OutboxErasureMessages`, `EraseModuleCodec`, `EraseModuleRelay` | Les messages comme enregistrements d'outbox de type `phil_privacy.erase_module`, le codec d'outbox d'`EraseModule`, qui écarte l'enregistrement d'une demande disparue, et la remise du message au bus | Implémenté ; branché une fois activé |
| `Infrastructure\Messenger\ErasureFailureListener` | Marque une étape comme demandant de l'attention quand Messenger abandonne son message | Implémenté ; branché une fois activé |
| `Infrastructure\Console\ErasureRequestCommand` | `phil:privacy:erasure` : une demande module par module, `--replay`, `--fail --confirm` | Implémenté ; branché une fois activé |
| `Infrastructure\Http\ErasureProgressController` | La page et le JSON de progression, pour la personne, un exploitant ou le détenteur du secret | Implémenté ; branché |
| `Infrastructure\DependencyInjection\ErasureWorkflowServices` | Les services du workflow d'effacement ; `EXTERNAL_ERASER_TAG`, `OPERATOR_ROLE` | Implémenté |
| `Infrastructure\Database\DbalEmailTrackingConsents` | Les choix des membres sur les courriels suivis, une ligne chacun | Implémenté |
| `Infrastructure\Database\UtcMoments` | Les instants des tables, en UTC et au seul format de la plateforme ; `text()`, `read()` | Implémenté |
| `Infrastructure\Mailer\MailerGuestLinkSender` | Le lien envoyé tout de suite par les transports du service de courriel | Implémenté ; branché une fois activé |
| `Infrastructure\Routing\GuestLinkOrigin` | Le lien généré sur `public_origin`, et le démarrage refusé en `prod` sans lui ni `framework.trusted_hosts` | Implémenté ; branché avec l'expéditeur par courriel |
| `Infrastructure\Doctrine\PrivacySchemaListener` | Ajoute les tables au schéma généré | Implémenté |
| `Infrastructure\Filesystem\EncryptedExportArchive` | Des fichiers scellés, privés, retirés à leur expiration | Implémenté |
| `Infrastructure\Log\LoggedConsentProofs` | Les preuves en lignes de journal | Implémenté |
| `Infrastructure\Security\SecurityCurrentDataSubject` | L'utilisateur connecté, par `DataSubjectUser` | Implémenté |
| `Infrastructure\Http\ConsentController`, `PreferencesController`, `PrivacyPages`, `ConsentCookie`, `ConsentContext`, `ConsentVaryListener` | Les pages, le témoin, l'état d'un visiteur et l'en-tête `Vary` | Implémenté ; branché |
| `Infrastructure\Http\GuestRequestController` | Les pages des demandes sans compte | Implémenté ; branché |
| `Infrastructure\Http\CookieInventoryFactory`, `UndeclaredCookies`, `UndeclaredCookieListener` | L'inventaire avec le témoin du consentement, les témoins non déclarés d'une réponse, l'avertissement en développement | Implémenté ; branché |
| `Infrastructure\Routing\PrivacyRoutes`, `MountingRouteLoader` | Les routes, et leur montage devant celles de l'application | Implémenté ; branché |
| `Infrastructure\Twig\ConsentExtension` | `privacy_consent()`, `privacy_consent_pending()`, `privacy_consent_banner()`, `privacy_cookie_policy()` | Implémenté ; branché |
| `Infrastructure\Twig\EmbedExtension` | `privacy_embed()` : l'adresse HTTPS du cadre vérifiée, une vidéo YouTube ou Vimeo changée en son lecteur sans témoins par `CookielessVideo` de `phil/url` ; `MAX_URL_LENGTH` | Implémenté ; branché |
| `Infrastructure\Twig\TwigExportReadme` | Le README d'une archive à partir de son gabarit | Implémenté ; branché |
| `Infrastructure\Testing\AssertsDeclaredCookies` | `assertResponseSetsDeclaredCookiesOnly()`, pour un test fonctionnel | Implémenté |
| `Infrastructure\Scheduler\PrivacySchedule` | Le calendrier `phil_privacy` | Implémenté ; branché avec `symfony/scheduler` |

## Hors du bundle

Aucun mappage Doctrine, et aucune découverte des classes `#[PersonalData]` hors des entités Doctrine et de
`register.classes`. Sauvegarder une base est le travail de `phil/backup-bundle`, et l'expiration d'un contenu publié
celui de `phil/content-expiry`.
