<!-- phil-dev source-sha256=1156659ac3b9cc7e255daa01e2a35b95286f9b749e1ac4fedd5b8bff6b38b277 -->
# Référence de la configuration

L'alias de l'extension est `phil_privacy`. L'arbre de configuration est défini dans `PhilPrivacyBundle::configure()` et
a ces clés : `log_redaction`, `public_origin`, `retention_schedule_source`, `retention`, `account_deletion`, `consent`,
`cookies`, `guest_requests`, `erasure_workflow`, `incidents`, `email_tracking`, `rights`, `pages`, `register`,
`database` et `anonymization`.

## Les clés de premier niveau

### `phil_privacy.public_origin`

L'adresse canonique du site, comme `https://example.org`, sur laquelle mènent les liens envoyés par courriel, ou `null`
(par défaut) : l'hôte de la requête, que `framework.trusted_hosts` doit alors borner. Dans l'environnement `prod`, tant
que `guest_requests` est activé avec l'expéditeur par courriel, le bundle refuse de démarrer quand ni l'un ni l'autre
n'est réglé. Voir [Où mène le lien](requests-without-an-account.md#où-mène-le-lien).

### `phil_privacy.retention_schedule_source`

Un identifiant de service d'une `RetentionScheduleSource`, ou `null` (par défaut). `ProcessingRegister` y lit le
calendrier pour montrer le stockage, la durée, le mécanisme et la réponse à l'effacement de chaque règle ; sans elle, le
registre ne nomme que les règles.

### `phil_privacy.retention`

| Clé | Par défaut | Effet |
|---|---|---|
| `frequency` | `1 day` | La fréquence à laquelle le calendrier `phil_privacy` applique le calendrier de conservation. |
| `rules` | `[]` | Le calendrier de conservation, par identifiant de règle, avec ce que dit de chaque stockage le registre des traitements (`purpose`, `recipients`, `processors`, `location`) ; voir [retention.md](retention.md). Refusée avec `retention_schedule_source`. |

### `phil_privacy.account_deletion`

| Clé | Par défaut | Effet |
|---|---|---|
| `delay` | `P30D` | Combien de temps une demande peut être annulée. Une durée nulle est refusée. |
| `frequency` | `15 minutes` | La fréquence à laquelle le calendrier `phil_privacy` cherche ce qui est échu. |
| `store` | `null` | Identifiant de service d'un `AccountDeletionStore` ; `null`, la table du bundle. |
| `export_archive` | `null` | Identifiant de service d'une `ExportArchive` ; `null`, des fichiers chiffrés. |
| `export_directory` | `%kernel.project_dir%/var/privacy-exports` | Où les fichiers chiffrés sont écrits. |
| `export_retention` | `P7D` | Combien de temps un export est gardé. |

Voir [account-deletion.md](account-deletion.md).

### `phil_privacy.consent`

| Clé | Par défaut | Effet |
|---|---|---|
| `version` | `1` | La version du texte ; changez-la et tout le monde est sollicité de nouveau. |
| `cookie_name` | `phil_privacy_consent` | Le témoin qui garde le choix. |
| `lifetime` | `P6M` | Combien de temps un choix est gardé. |
| `proofs` | `null` | Identifiant de service de `ConsentProofs` ; `null`, la table du bundle, ou le journal sans base de données. |
| `categories` | `[]` | Par nom : `necessary`, `labels` et `descriptions` par locale. |

Voir [consent.md](consent.md).

### `phil_privacy.cookies`

| Clé | Par défaut | Effet |
|---|---|---|
| `inventory` | `null` | Le fichier YAML de l'inventaire des témoins ; `null`, le `config/privacy/cookies.yaml` du projet. Un fichier qui n'existe pas ne déclare rien. |
| `watch` | `null` | Journalise un avertissement pour une réponse qui pose un témoin non déclaré ; `null`, quand `kernel.debug` vaut `true`. |

Voir [cookie-inventory.md](cookie-inventory.md).

### `phil_privacy.guest_requests`

Les demandes d'accès et d'effacement des personnes sans compte, inactives par défaut ; chaque clé est dans
[requests-without-an-account.md](requests-without-an-account.md#le-lien).

### `phil_privacy.erasure_workflow`

L'effacement comme workflow, inactif par défaut ; voir [erasure-workflow.md](erasure-workflow.md).

| Clé | Défaut | Effet |
|---|---|---|
| `enabled` | `false` | Une étape par module, envoyée par phil/outbox-bundle, réessayée et terminée séparément ; les suppressions de compte et les demandes sans compte effacent par ce moyen |
| `lease` | `900` | Les secondes pendant lesquelles un *worker* garde une étape, de 1 à 86 400 : plus long que l'effaceur le plus lent |
| `operator_role` | `ROLE_PRIVACY_OPERATOR` | Le rôle qui peut lire la progression de n'importe quelle demande d'effacement |
| `store` | `null` | L'identifiant de service d'un `ErasureRequestStore` ; `null`, les tables du bundle |

### `phil_privacy.incidents`

`store` : l'identifiant de service d'un `IncidentRegister` ; `null` (par défaut), la table du bundle. Voir
[incidents.md](incidents.md).

### `phil_privacy.email_tracking`

Le consentement de chaque membre aux courriels suivis, inactif par défaut. Voir [email-tracking.md](email-tracking.md).

| Clé | Défaut | Effet |
|---|---|---|
| `enabled` | `false` | Demande sur la page de préférences, et enregistre `EmailTrackingConsents`. |
| `version` | `'1'` | La version du texte que lit le membre ; la changer arrête chaque choix antérieur jusqu'à ce que le membre choisisse de nouveau. |
| `store` | `null` | L'identifiant de service d'un `EmailTrackingStore` ; `null`, la table `phil_privacy_email_tracking`. |

### `phil_privacy.rights`

> [!CAUTION]
> **L'export et l'effacement d'un membre sont actifs par défaut, et doivent le rester.** La plupart des lois sur la vie
> privée donnent à chacun le droit d'obtenir et d'effacer ses données ; en désactiver un retire ce droit à tous les
> membres d'un coup. Le bundle en fait un geste délibéré et visible : aucun booléen, la phrase entière ci-dessous, un
> avertissement encadré à chaque construction du conteneur et une ligne rouge `DANGER` avant chaque commande de la console.

`export.disable` et `erasure.disable` valent `null`, le droit étant actif. Chacune accepte une seule valeur : la phrase
entière de `PrivacyRights`, que cite l'erreur de construction. Voir [preferences.md](preferences.md).

### `phil_privacy.pages`

| Clé | Par défaut | Effet |
|---|---|---|
| `auto_mount` | `true` | Monte les pages ; `false`, l'application importe `@PhilPrivacyBundle/config/routes.php`. |
| `prefix` | `/privacy-choices` | Le chemin des pages. |
| `layout` | `@PhilPrivacy/layout.html.twig` | Le gabarit qu'étendent les pages. |
| `current_subject` | `null` | Identifiant de service d'un `CurrentDataSubject` ; `null`, l'utilisateur connecté par `DataSubjectUser`. |
| `clock` | `clock` | Identifiant de service de l'horloge PSR-20. |

### `phil_privacy.register.classes`

Une liste de noms de classes dont le registre lit les `#[PersonalData]`, à côté de chaque entité Doctrine ; `[]` par
défaut. Une valeur qui n'est pas une chaîne fait échouer la construction :
`phil_privacy.register.classes must be a list of strings.`

### `phil_privacy.database`

La base sur laquelle travaille `phil:privacy:anonymize` ([anonymization.md](anonymization.md)).

| Clé | Type | Par défaut | Effet |
|---|---|---|---|
| `connection` | chaîne non vide | `database_connection` | Identifiant de service de la connexion Doctrine DBAL. Quand ce n'est pas un service de l'application, les services de base de données et la commande sont retirés plutôt que de faire échouer le conteneur. |

### `phil_privacy.anonymization`

Comment `phil:privacy:anonymize` réécrit les données personnelles ([anonymization.md](anonymization.md)).

| Clé | Type | Par défaut | Effet |
|---|---|---|---|
| `locale` | chaîne non vide | `en_US` | Le paquet de locale où puisent les anonymiseurs, sauf si une colonne donne une option `locale`. |
| `batch_size` | entier, au moins 1 | `500` | Les lignes lues par requête. |
| `categories` | correspondance de catégorie de données personnelles à type d'anonymiseur, clés gardées telles qu'écrites | `{}` | L'anonymiseur de chaque champ `#[PersonalData]` sans `#[Anonymize]`. |
| `tables` | correspondance de table à correspondance de colonne à un type ou à `{ type, options }`, clés gardées telles qu'écrites | `{}` | Des règles qui remplacent celles des attributs et des catégories, et atteignent les tables qu'aucune entité ne mappe. |
| `guard.environments` | liste de chaînes | `[prod]` | Les environnements du noyau où l'anonymisation est refusée. |
| `guard.hosts` | liste de chaînes | `[]` | Les hôtes de base de données sur lesquels l'anonymisation est refusée. |
| `guard.databases` | liste de chaînes | `[]` | Les noms de base de données, ou fichiers SQLite, sur lesquels l'anonymisation est refusée. |

## Exemple complet annoté

```yaml
# config/packages/phil_privacy.yaml
phil_privacy:
    log_redaction:
        # Keys whose values never reach a log line. Compared without case or separators
        # ("_", "-", ".", spaces), and exactly: "code" does not cover "postal_code".
        # An entry that starts and/or ends with "*" is a pattern over a family of keys.
        denylist:
            - '*password*'      # password, plainPassword, db_password, --db-password ...
            - '*token*'         # token, refresh_token, csrfToken ...
            - '*secret*'
            - '*seed*'
            - '*authorization*' # Authorization, Proxy-Authorization headers
            - '*cookie*'
            - code              # exact: postal_code and country_code stay readable
            - '*apikey'         # api_key, X-Api-Key
            - query_string      # a raw query string has no "?" to be recognised by
        # Path prefixes whose next segment is a token: "/p/abc/edit" becomes "/p/[redacted]/edit".
        token_path_prefixes:
            - /p/
        # Short command line options: each letter to the long option name it abbreviates.
        # The value is redacted only when the denylist denies that long name.
        short_options:
            p: password
    database:
        connection: database_connection   # a Doctrine DBAL connection service
    anonymization:
        locale: fr_CA
        categories: { email: email, person_name: name, phone: phone }
        tables:
            member:
                note: { type: lorem, options: { words: 6 } }
        guard:
            environments: [prod, staging]
            hosts: [db.example.com]
```

## Les clés

### `phil_privacy.log_redaction`

Nœud de tableau, valeurs par défaut ajoutées s'il n'est pas défini : omettre la section, ou toute la clé `phil_privacy`,
revient à écrire chaque enfant avec sa valeur par défaut. Toute autre clé sous `phil_privacy` est refusée par le
composant Config (`Unrecognized option ... under "phil_privacy"`).

### `phil_privacy.log_redaction.denylist`

| | |
|---|---|
| Type | liste de scalaires, chacun une chaîne |
| Par défaut | `[]` |
| Effet | Construit la `Denylist` qu'utilise chaque caviardeur. |

- Une entrée est normalisée : rognée, privée de `_`, `-`, `.` et des espaces, et mise en minuscules. Deux entrées de
  même forme normalisée sont une seule entrée.
- `stem*` refuse chaque clé qui commence par la racine, `*stem` chaque clé qui finit par elle, `*stem*` chaque clé qui
  la contient. La racine est normalisée comme une clé.
- Un `*` ailleurs (`se*ed`) fait échouer la construction, comme une entrée vide une fois normalisée (`--`, `*`).
- Les listes de plusieurs fichiers de configuration sont mises bout à bout, comme toute liste à prototype dans Symfony.
- La même liste gouverne trois choses : les clés du contexte et de `extra`, les noms d'options longues dans les lignes
  de commande, et les noms longs vers lesquels pointe `short_options`. Voir [log-redaction.md](log-redaction.md).

### `phil_privacy.log_redaction.token_path_prefixes`

| | |
|---|---|
| Type | liste de scalaires, chacun une chaîne |
| Par défaut | `[]` |
| Effet | Construit l'`UrlRedactor` : le segment de chemin qui suit chaque préfixe est remplacé par `[redacted]`. |

- Un préfixe est un ou plusieurs segments de chemin entre barres obliques : `/p/`, `/share/link/`. Il doit commencer et
  finir par `/` et ne contenir ni espace, ni `?`, ni `#` ; tout le reste (`p`, `/p`) fait échouer la construction.
- Un préfixe ne correspond que là où commence un chemin : au début d'une chaîne, après un espace, un guillemet, `(`, `=`
  ou `<`, ou juste après un schéma et un hôte. `/shop/p/abc` n'est pas sous `/p/`.

### `phil_privacy.log_redaction.short_options`

| | |
|---|---|
| Type | correspondance de lettre à chaîne ; les clés sont gardées telles qu'écrites (`normalizeKeys(false)`) |
| Par défaut | `{}` |
| Effet | La table des options courtes du `CommandLineRedactor`. |

- Chaque clé doit être exactement une lettre ASCII, qui respecte la casse (`p` et `P` sont deux options). `pw` ou `-p`
  fait échouer la construction.
- Chaque valeur doit être un nom d'option longue sans ses tirets, correspondant à `[A-Za-z0-9][A-Za-z0-9_.-]*`
  (`password`, `db-password`). Une valeur qui n'est pas une chaîne fait échouer la construction.
- Une lettre n'est caviardée que si la liste de refus refuse son nom long ; une lettre associée à un nom permis
  (`P: port`) est acceptée et n'a aucun effet.
- Vide, ce qui est le défaut, aucune option courte n'est lue.

## Les services branchés

| Identifiant de service | Classe | Visibilité | Définition |
|---|---|---|---|
| `Phil\PrivacyBundle\Infrastructure\Monolog\RedactingProcessor` | `RedactingProcessor` | privé | Construit par la fabrique `RedactingProcessor::fromConfiguration($denylist, $tokenPathPrefixes, $shortOptions)`, étiqueté `monolog.processor` sans attributs. |
| `Phil\PrivacyBundle\Application\SubjectExport`, `SubjectErasure` | — | public | Sur chaque service étiqueté `phil_privacy.exporter`, `phil_privacy.eraser` : les étiquettes que l'autoconfiguration donne à `PersonalDataExporter` et à `PersonalDataEraser`. |
| `Phil\PrivacyBundle\Application\Register\ProcessingRegister` | — | public | Sur `retention_schedule_source`, ou aucune. |
| `ExportSubjectCommand`, `EraseSubjectCommand`, `ProcessingRegisterCommand` | — | privé | `phil:privacy:export`, `phil:privacy:erase`, `phil:privacy:register`, avec Symfony Console ; la dernière avec `register.classes` et le registre `doctrine` quand il y en a un. |
| `Phil\PrivacyBundle\Domain\Anonymization\Anonymizers` | — | privé | Les anonymiseurs intégrés avec chaque service étiqueté `phil_privacy.anonymizer`, par son attribut `type` : l'étiquette que donne `#[AsAnonymizer]`. |
| `Phil\PrivacyBundle\Domain\Anonymization\Locale\LocalePacks` | — | privé | Les paquets intégrés avec chaque service `LocalePack`, étiqueté `phil_privacy.locale_pack` par l'autoconfiguration. |
| `Phil\PrivacyBundle\Application\Anonymization\AnonymizationRun` | — | public | Sur `Anonymizers`, `LocalePacks` et `anonymization.locale`. |
| `phil_privacy.database_identity` | `DatabaseIdentity` | privé | Construit par `ConnectionIdentity::of()` à partir de `database.connection`, sans se connecter. |
| `DbalAnonymizationTarget`, `AnonymizationPlanReader`, `ProductionGuard` | — | privé | À partir de `database.connection` et de `anonymization.batch_size`, du registre `doctrine` quand il y en a un avec `categories` et `tables`, de `%kernel.environment%` et de `guard`. |
| `AnonymizeDatabaseCommand` | — | privé | `phil:privacy:anonymize`. |
| `RetentionRun`, `RetentionJob` | — | public | Sur `retention_schedule_source` ou `retention.rules`, et chaque service étiqueté `phil_privacy.retention_purger` ; absents sans calendrier. |
| `AccountDeletions` | — | public | Le workflow, `lock.factory`, `SubjectExport`, `SubjectErasure`, le stockage et l'archive ; absent sans stockage, sans verrou ou sans horloge. |
| `phil_privacy.account_deletion_workflow` | `StateMachine` | public | Étiqueté `workflow`, nommé `phil_privacy_account_deletion`. |
| `ConsentContext`, `ConsentPolicy` | — | public | Le consentement du visiteur, et les catégories et la version de la configuration, chaque catégorie avec les noms que lui donne l'inventaire. |
| `CookieInventory` | — | public | L'inventaire de `cookies.inventory`, avec le témoin du consentement. |
| `UndeclaredCookieListener` | — | privé | Sur `kernel.response`, avec `cookies.watch` ou en mode débogage, quand il y a un journal. |
| `EmbedExtension` | — | privé | `privacy_embed()`. |
| `ExportPackage`, `ExportReadme` | — | public, privé | L'archive d'une réponse d'accès ; le README par Twig, ou en anglais simple sans Twig. |
| `IncidentRegister`, `DbalIncidentRegister`, `IncidentCommand`, `IncidentsCommand` | — | alias public, privé | Le registre des incidents et ses commandes ; absents sans base de données sauf si un stockage est nommé. |
| `IncidentNotices`, `TwigIncidentNotice`, `IncidentNoticeCommand` | — | privé | Les brouillons des avis d'un incident ; la commande est absente sans le registre, une horloge, Twig ou le traducteur. |
| `NoticeOrganisationSource`, `SiteIdentityOrganisation` | — | privé | Qui donne les avis, d'après l'identité du site quand phil/legal-notices-bundle l'enregistre et que l'application ne nomme aucune source propre. |
| `EmailTrackingConsents`, `EmailTrackingStore`, `DbalEmailTrackingConsents` | — | public, alias privé, privé | Le consentement aux courriels suivis, étiqueté `phil_privacy.exporter` et `phil_privacy.eraser` ; seulement quand `email_tracking.enabled`. |
| `GuestRequests`, `GuestRequestController`, `DbalGuestRequests`, `MailerGuestLinkSender`, `phil_privacy.guest_requests.per_client`, `phil_privacy.guest_requests.per_address` | — | public, privé | Les demandes sans compte, leurs pages et leurs deux fabriques de limiteurs de débit ; `GuestRequests` et les limiteurs seulement quand `guest_requests.enabled`. |
| `ErasureRequests`, `DbalErasureRequests`, `EraseModuleHandler`, `EraseModuleCodec`, `EraseModuleRelay`, `ErasureFailureListener`, `ErasureRequestCommand` | — | public, privé | Le workflow d'effacement, seulement quand `erasure_workflow.enabled` ; `ErasureRequests` est public. |
| `ErasureProgressController` | — | public | La page et le JSON de progression d'une demande d'effacement ; un 404 tant que le workflow est inactif. |
| `PrivacySchedule` | — | privé | Le calendrier `phil_privacy`, avec `symfony/scheduler` et un bus de messages. |

Les services de base de données et la commande sont enregistrés quand Symfony Console et Doctrine DBAL sont installés,
et retirés par `DatabaseServicesPass` quand `database.connection` n'est pas un service.

`PrivacyServicesPass` retire ce qu'une application ne peut pas faire tourner : sans base de données, les preuves de
consentement deviennent des lignes de journal, et les suppressions, les incidents et les demandes sans compte demandent
un stockage nommé ; sans Twig, sans jetons CSRF et sans routeur, il n'y a pas de pages ; sans pare-feu, restent le
consentement, la politique des témoins et les demandes sans compte. Les demandes sans compte, une fois activées,
demandent chacune de leurs parties — un stockage, un expéditeur, le verrou, l'horloge, le réservoir de cache, et Twig,
le routeur et les transports du service de courriel pour l'expéditeur par courriel —, et une partie manquante fait
échouer la construction par une `LogicException` qui la nomme. Le consentement aux courriels suivis, une fois activé,
demande de la même façon un stockage et l'horloge.

Avec AssetMapper, le bundle ajoute son répertoire `assets/src` aux chemins de `asset_mapper` sous l'espace de noms
`@phil/privacy-bundle`, pour que StimulusBundle trouve les contrôleurs `consent` et `embed` que sa recette active.

L'étiquette `monolog.processor` ne porte aucun attribut `channel`, `handler` ou `priority` : MonologBundle ajoute donc
le processeur à chaque journal de canal à la priorité par défaut. Voir [log-redaction.md](log-redaction.md) pour ce que
cela veut dire de l'ordre des processeurs.

## La validation à la construction

L'extension construit le processeur une fois pendant le chargement : une entrée mal formée fait échouer la construction
du conteneur plutôt que la première ligne de journal. Chaque erreur est une
`Symfony\Component\Config\Definition\Exception\InvalidConfigurationException` :

| Cause | Message (début) |
|---|---|
| Une entrée de la liste de refus qui n'est pas une chaîne (un nombre, un booléen) | `phil_privacy.log_redaction.denylist must be a list of strings.` |
| Un préfixe de chemin à jeton qui n'est pas une chaîne | `phil_privacy.log_redaction.token_path_prefixes must be a list of strings.` |
| Une valeur d'option courte qui n'est pas une chaîne | `phil_privacy.log_redaction.short_options must map letters to long option names.` |
| Un `*` au milieu d'une racine de la liste de refus | `phil_privacy.log_redaction: "se*ed" is not a denylist key: "*" may only open or close a pattern ...` |
| Une entrée de la liste de refus vide une fois normalisée | `phil_privacy.log_redaction: "--" is not a denylist key: nothing is left once case, separators and pattern stars are dropped.` |
| Un préfixe de chemin à jeton mal formé | `phil_privacy.log_redaction: "p" is not a token path prefix: expected one or more path segments between slashes, such as "/p/".` |
| Une clé d'option courte qui n'est pas une seule lettre | `phil_privacy.log_redaction: "pw" is not a short option: a short option is one ASCII letter, such as "p".` |
| Un type d'anonymiseur mal formé sous `anonymization.tables` | `phil_privacy.anonymization: "E-mail" is not an anonymizer type: lowercase letters, digits and underscores, starting with a letter.` |
| Une règle de table dont les options ne sont pas des scalaires nommés | `phil_privacy.anonymization.tables.member.note needs a type, and named options whose values are scalars or null.` |
| Une catégorie associée à autre chose qu'une chaîne | `phil_privacy.anonymization.categories must map names to strings.` |
| Un droit désactivé par autre chose que sa phrase | `phil_privacy.rights.export.disable must be exactly this sentence: "…".` |
| Une durée qui n'en est pas une, ou qui est nulle | `phil_privacy.account_deletion.delay: "x" is not an ISO 8601 duration …` |
| Un nom de catégorie ou une version de texte mal formés | `phil_privacy.consent: "A B" is not a consent category …` |
| Une ligne de conservation que refuse le modèle | `phil_privacy.retention.rules: …` |
| Une période fixe de moins de cinq ans sur `phil_privacy_incident` (article 8 du règlement québécois, r. 3.1) | `phil_privacy.retention.rules.R-INC: the register of confidentiality incidents is kept at least five years after the discovery …` |
| À la fois une source et des règles | `phil_privacy: name retention_schedule_source or write retention.rules, not both …` |
| Un inventaire des témoins que refuse le modèle, ou un fichier qui n'est pas du YAML d'une correspondance | `phil_privacy.cookies.inventory: …` |
| Les demandes sans compte activées sans expéditeur | `phil_privacy.guest_requests.from is the sender of the links …` |
| Les demandes sans compte activées sans le limiteur de débit | `phil_privacy.guest_requests needs symfony/rate-limiter …` |
| Une durée de vie de lien qui n'est pas une durée | `phil_privacy.guest_requests.link_lifetime: …` |
| Le workflow d'effacement activé sans phil/outbox-bundle, sans magasin, sans Messenger ou sans l'horloge | `phil_privacy.erasure_workflow is enabled and needs …` |
| Un bail d'effacement hors de 1 à 86 400 secondes, ou un rôle d'exploitant vide | `Invalid configuration for path "phil_privacy.erasure_workflow.…"` |
| Une origine avec un chemin, une requête, un fragment ou une adresse IP, ou `localhost` hors du mode débogage, lue au démarrage | `"phil_privacy.public_origin" is an origin …`, `"phil_privacy.public_origin" is the canonical address of the site …` |
| En `prod`, les demandes activées avec l'expéditeur par courriel, ni `public_origin` ni `framework.trusted_hosts`, au démarrage | `The links of the requests without an account would take the host of the request: …` |
| Une version malformée du texte sur les courriels suivis | `phil_privacy.email_tracking.version: letters, digits, ".", "_" and "-", 32 at most, …` |
| Un nom d'option longue mal formé | `phil_privacy.log_redaction: The short option "p" must map to a long option name without its dashes, such as "password".` |

Les erreurs du domaine derrière les messages `phil_privacy.log_redaction:` sont des `InvalidPrivacyValue`, gardées comme
exception précédente. Une valeur de la mauvaise forme (une chaîne là où une liste est attendue, un tableau comme valeur
d'option courte) est refusée plus tôt par le composant Config lui-même, avec sa propre `InvalidTypeException`.
