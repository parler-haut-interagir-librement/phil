<!-- phil-dev source-sha256=cdd2a7a638098aba6b0037ace162080da0a5648f7785d4c2cf85a1677a005cd2 -->
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
| `RetentionPeriod` | `fixed()`, `untilEvent()`, `indefinite()`, `notYetFixed()` ; `isFixed()`, `cutoff($now)` en UTC avec la fin de mois ramenée | Implémenté |
| `RetentionPeriodKind` | Énumération : `Fixed`, `UntilEvent`, `Indefinite`, `NotYetFixed` | Implémenté |
| `RetentionRule` | Une ligne du calendrier ; refuse une tâche sur une durée indéfinie et `AwaitsExpiry` sans durée fixe ; `ID_PATTERN` ; `holdsPersonalInformation()` ; `$processing` | Implémenté |
| `Processing` | Ce que dit le registre d'un stockage : `$purpose`, `$recipients`, `$processors`, `$location` ; `isComplete()` | Implémenté |
| `Processor` | Un fournisseur de services et l'endroit où il garde les enregistrements ; `toString()` | Implémenté |
| `RetentionSchedule` | Les lignes par identifiant ; `has()`, `rule()`, `rules()`, `purgeOrders($now)`, `jobsWithoutCutoff()` | Implémenté |
| `PurgeOrder` | `$ruleId`, `$store`, `$cutoff` | Implémenté |
| `Mechanism` | Énumération : `Job`, `Ttl`, `Rotation`, `Event`, `Manual` | Implémenté |
| `ErasureEffect` | Énumération : `Deleted`, `Pseudonymised`, `Redacted`, `AwaitsExpiry`, `Survives` | Implémenté |
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
| `Incident` | Une ligne du registre des incidents ; `record()`, `update()`, `isClosed()`, `pendingNotifications()` | Implémenté |

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
| `GuestRequest\GuestRequests` | Les demandes sans compte ; `submit()`, `open()`, `access()`, `erase()` | Implémenté ; branché une fois activé |
| `GuestRequest\EmailGuestSubjects` | L'adresse vérifiée comme personne concernée | Implémenté ; branché une fois activé |
| `PrivacyRights` | Quels droits sont actifs ; les deux phrases ; `warnings()` | Implémenté ; branché |
| `Message\AdvanceAccountDeletion`, `Message\ProcessDueAccountDeletions`, `Message\RunRetention` | Des messages, identifiants seulement | Implémenté |
| `Handler\AdvanceAccountDeletionHandler`, `Handler\ProcessDueAccountDeletionsHandler`, `Handler\RunRetentionHandler` | Leurs gestionnaires | Implémenté ; branché |
| `Anonymization\AnonymizationRun` | Réécrit les colonnes d'un plan en une transaction ; `run($plan, $target, ?$seed, ?$salt)` | Implémenté ; branché |
| `Anonymization\AnonymizationReport` | Les lignes réécrites par table ; `total()` | Implémenté |
| `Anonymization\ProductionGuard` | Refuse les environnements de production et les hôtes et bases protégés ; `check($database)` | Implémenté ; branché |

### `Application\Contract`

| Type | Rôle | État |
|---|---|---|
| `PersonalDataExporter` | `module()`, `export($subject)` | Port |
| `PersonalDataEraser` | `module()`, `erase($subject)` ; idempotent | Port |
| `RetentionScheduleSource` | `load()` | Port |
| `RetentionPurger` | `store()`, `purge($order)` | Port |
| `AnonymizationTarget` | `keyOf()`, `rows()`, `update()`, `transactional()` | Port ; `DbalAnonymizationTarget` |
| `AccountDeletionStore` | `find()`, `openFor()`, `save()`, `dueIdentifiers()` | Port ; `DbalAccountDeletions` |
| `ExportArchive` | `put()`, `get()`, `purgeExpired()` | Port ; `EncryptedExportArchive` |
| `ConsentProofs` | `record()` | Port ; `DbalConsentProofs`, `LoggedConsentProofs` |
| `CurrentDataSubject` | `current()` | Port ; `SecurityCurrentDataSubject` |
| `DataSubjectUser` | `dataSubjectIdentifier()`, sur la classe de l'utilisateur de l'application | Port |
| `ExportReadme` | `render($modules, $madeAt)` | Port ; `TwigExportReadme`, `PlainExportReadme` |
| `GuestRequestStore` | `save()`, `find()`, `delete()`, `purgeExpired()` | Port ; `DbalGuestRequests` |
| `GuestLinkSender` | `send($request, $token)` | Port ; `MailerGuestLinkSender` |
| `GuestSubjects` | `subjectOf($email)` | Port ; `EmailGuestSubjects` |
| `IncidentRegister` | `save()`, `find()`, `all()` | Port ; `DbalIncidentRegister` |
| `EmailTrackingStore` | `find()`, `save()`, `forget()`, `store()` | Port ; `DbalEmailTrackingConsents` |

### `Application\Exception`

| Type | Rôle | État |
|---|---|---|
| `ErasureIncomplete` | `RuntimeException` ; les rapports `$completed`, les `$failures` par module ; `after($completed, $failures)` | Implémenté |
| `MisconfiguredPrivacy` | `LogicException` ; `duplicateModule()`, `duplicatePurger()`, `missingPurger()`, `userWithoutSubject()` | Implémenté |
| `AccountDeletionBusy` | `RuntimeException` ; un autre processus détient le verrou de cette personne | Implémenté |
| `LockLost` | `RuntimeException` ; le verrou d'un travail par étapes a expiré, a été pris, ou n'a pas pu être confirmé ; `create()` | Implémenté |
| `AnonymizationRefused` | `RuntimeException` ; `inEnvironment()`, `protectedDatabase()`, `withoutKey()`, `keyColumn()` | Implémenté |
| `TooManyGuestRequests` | `RuntimeException` ; un client au-delà de sa limite ; `retryIn()`, `retryAfter()` | Implémenté |

## Infrastructure

| Type | Rôle | État |
|---|---|---|
| `Infrastructure\Monolog\RedactingProcessor` | Processeur Monolog sur un `ContextRedactor` ; `fromConfiguration()`, `__invoke(LogRecord)` | Implémenté ; branché et étiqueté `monolog.processor` |
| `Infrastructure\Console\ExportSubjectCommand` | `phil:privacy:export` : la réponse à une demande d'accès, affichée ou écrite dans un fichier `0600` (`--output`, `--force`) | Implémenté ; branché avec Symfony Console |
| `Infrastructure\Console\EraseSubjectCommand` | `phil:privacy:erase` : l'effacement de chaque module, avec `--confirm` | Implémenté ; branché avec Symfony Console |
| `Infrastructure\Console\ProcessingRegisterCommand` | `phil:privacy:register` : le registre des traitements, en Markdown ou en JSON, `--strict` | Implémenté ; branché avec Symfony Console |
| `Infrastructure\Console\IncidentCommand`, `IncidentsCommand` | `phil:privacy:incident`, `phil:privacy:incidents` | Implémenté ; branché avec Symfony Console |
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
| `Infrastructure\Database\DbalIncidentRegister` | Le registre des incidents ; purge aussi | Implémenté |
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
