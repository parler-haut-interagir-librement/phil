<!-- phil-dev source-sha256=f85dae0e2c2e3d27faf1bec59c0ff62d19e234fc9028a558d4de8a090d639aa5 -->
# Points d'extension

Toute interface du bundle est un contrat, sous `Phil\PrivacyBundle\Application\Contract`, ou sous
`Phil\PrivacyBundle\Domain\Contract` pour `Anonymizer`. L'application implémente `PersonalDataExporter`,
`PersonalDataEraser`, `RetentionScheduleSource` et `RetentionPurger`, et le bundle n'en enregistre aucun ; sa classe
d'utilisateur implémente `DataSubjectUser` ; un `Anonymizer` ajoute un type à l'anonymisation. Chacun des autres contrats
a par défaut un adaptateur du bundle, que la configuration remplace en nommant un service.

`Phil\PrivacyBundle\Application\Port\PersonalDataExporter` et `PersonalDataEraser` sont des noms obsolètes qui étendent
ces deux contrats, pour qu'un module qui les implémente soit encore recueilli ; implémentez plutôt les contrats.

## `PersonalDataExporter`

La part d'un module dans une réponse d'accès ou de portabilité. Consommé par `SubjectExport`.

| Méthode | Contrat |
|---|---|
| `module(): ModuleName` | Le nom du module. Unique parmi les exportateurs, stable dans le temps : c'est la clé de la section du module. |
| `export(DataSubject $subject): array<string, mixed>` | Les enregistrements du module sur la personne, encodables en JSON, indexés comme le module l'entend. `[]` quand il ne détient rien. |

Les invariants que garde un adaptateur :

- construit à partir des modèles de lecture qu'interroge déjà le module, et non d'une vue parallèle ;
- ne rend que des données sur `$subject` ;
- rend des valeurs encodables en JSON (scalaires, tableaux, `null` ; formatez vous-même les dates) ;
- en lecture seule : un export ne change rien ;
- finit dans la durée de vie du verrou sous lequel il s'exécute, cinq minutes pour une demande sans compte ou un
  téléchargement, quinze pour une suppression de compte : le verrou est renouvelé entre les modules, pas pendant l'un
  d'eux ([Verrous et longs travaux](export-and-erasure.md#verrous-et-longs-travaux)).

```php
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

final readonly class BillingExporter implements PersonalDataExporter
{
    public function __construct(private InvoiceReadModel $invoices) {}

    public function module(): ModuleName
    {
        return ModuleName::fromString('billing');
    }

    public function export(DataSubject $subject): array
    {
        $invoices = $this->invoices->forCustomer($subject->value);

        return [] === $invoices ? [] : ['invoices' => array_map(
            static fn (Invoice $i): array => ['number' => $i->number, 'issued_at' => $i->issuedAt->format(DATE_ATOM)],
            $invoices,
        )];
    }
}
```

## `PersonalDataEraser`

La part d'un module dans un effacement. Consommé par `SubjectErasure`.

| Méthode | Contrat |
|---|---|
| `module(): ModuleName` | Le nom du module. Unique parmi les effaceurs. |
| `erase(DataSubject $subject): ErasureReport` | Efface les enregistrements du module sur la personne et rapporte chaque stockage qu'il détient. |

Les invariants que garde un adaptateur :

- **idempotent** : une seconde exécution sur une personne déjà effacée réussit et rapporte les mêmes effets (relances,
  rejeux après la restauration d'une sauvegarde) ;
- le rapport nomme **ce** module (`ErasureReport::of($this->module()->value, ...)`) ; un rapport sous un autre nom est
  noté comme un échec ;
- le rapport nomme **chaque** stockage que détient le module, avec `Survives` ou `AwaitsExpiry` là où rien n'est retiré
  ;
- lève en cas d'échec plutôt que de rendre un rapport partiel : `SubjectErasure` note l'exception et continue avec les
  autres modules ;
- finit dans la durée de vie du verrou sous lequel il s'exécute, cinq minutes pour une demande sans compte, quinze pour
  une suppression de compte : un *worker* qui reprend un verrou perdu réexécute chaque effaceur, ce que l'idempotence
  rend sûr ([Verrous et longs travaux](export-and-erasure.md#verrous-et-longs-travaux)).

```php
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

final readonly class BillingEraser implements PersonalDataEraser
{
    public function __construct(private Connection $db) {}

    public function module(): ModuleName
    {
        return ModuleName::fromString('billing');
    }

    public function erase(DataSubject $subject): ErasureReport
    {
        // Idempotent: on an already pseudonymised row this sets the same values again.
        $this->db->executeStatement(
            'UPDATE invoices SET customer_name = NULL, customer_email = NULL WHERE customer_id = ?',
            [$subject->value],
        );

        return ErasureReport::of('billing', [
            'invoices' => ErasureEffect::Survives,        // kept under a legal obligation, without identity
            'payment_logs' => ErasureEffect::AwaitsExpiry,
        ]);
    }
}
```

## `RetentionScheduleSource`

Lit le calendrier de conservation de l'application, où et comme il est tenu. Consommé par `RetentionRun`. Un calendrier
peut aussi s'écrire sous `retention.rules`, sans source.

| Méthode | Contrat |
|---|---|
| `load(): RetentionSchedule` | Le calendrier en vigueur. Les erreurs de construction des règles et des durées remontent ici comme `InvalidPrivacyValue`. |

Invariants : les identifiants sont stables (les déclarations les citent) ; `load()` est appelée une fois par
`RetentionRun::run()`.

```php
use Phil\PrivacyBundle\Application\Contract\RetentionScheduleSource;
use Phil\PrivacyBundle\Domain\Retention\{AccessResponse, ErasureEffect, Mechanism, RetentionPeriod, RetentionRule, RetentionSchedule};
use Phil\PrivacyBundle\Domain\Subject\DataCategory;
use Symfony\Component\Yaml\Yaml;

final readonly class YamlScheduleSource implements RetentionScheduleSource
{
    public function __construct(private string $path) {}

    public function load(): RetentionSchedule
    {
        $rules = [];

        foreach (Yaml::parseFile($this->path) as $id => $line) {
            $rules[] = new RetentionRule(
                (string) $id,
                $line['store'],
                array_map(DataCategory::fromString(...), $line['categories']),
                isset($line['duration']) ? RetentionPeriod::fixed($line['duration']) : RetentionPeriod::untilEvent($line['until']),
                Mechanism::from($line['mechanism']),
                ErasureEffect::from($line['erasure']),
                AccessResponse::from($line['access']),
            );
        }

        return new RetentionSchedule($rules);
    }
}
```

(`symfony/yaml` est une dépendance de l'application dans cet exemple, pas du bundle.)

## `RetentionPurger`

Purge un stockage de ce qui a dépassé sa durée. Consommé par `RetentionRun`.

| Méthode | Contrat |
|---|---|
| `store(): string` | Le stockage dont répond ce purgeur, exactement comme le nomme le calendrier. Unique parmi les purgeurs. |
| `purge(PurgeOrder $order): int` | Retire les enregistrements de `$order->store` plus anciens que `$order->cutoff` que couvre `$order->ruleId` ; rend combien. |

Invariants : comparer strictement à la date limite (« plus ancien que »), en UTC ; ne purger que ce que couvre la règle
quand plusieurs règles partagent un stockage ; pouvoir être relancé sans risque (une seconde exécution sur la même date
limite ne purge rien de plus) ; finir dans l'heure du verrou de la tâche, qui est renouvelé entre les ordres et non
pendant l'un d'eux, en purgeant un grand stockage par lots. Que la purge soit un `DELETE`, la suppression d'une
partition ou celle d'un objet est le choix de l'adaptateur.

```php
use Phil\PrivacyBundle\Application\Contract\RetentionPurger;
use Phil\PrivacyBundle\Domain\Retention\PurgeOrder;

final readonly class ClickPurger implements RetentionPurger
{
    public function __construct(private Connection $db) {}

    public function store(): string
    {
        return 'clicks';
    }

    public function purge(PurgeOrder $order): int
    {
        return (int) $this->db->executeStatement(
            'DELETE FROM clicks WHERE created_at < ?',
            [$order->cutoff->format('Y-m-d H:i:s')],
        );
    }
}
```

## `AccountDeletionStore`

Garde les suppressions de compte entre la demande et la fin du délai. La table du bundle est le défaut ; nommez-en un
autre avec `account_deletion.store`.

| Méthode | Contrat |
|---|---|
| `find(string $id)` | La demande, ou `null`. Une copie fraîche : la demande est relue une fois son verrou obtenu. |
| `openFor(DataSubject $subject)` | La demande de cette personne qui est `requested` ou `exported`, ou `null`. |
| `save(AccountDeletion $deletion)` | Crée ou remplace la ligne. |
| `dueIdentifiers(DateTimeImmutable $now, int $limit)` | Les demandes ouvertes dont `effectiveAt` est passée, la plus ancienne en premier. |

## `ExportArchive`

Garde l'export écrit avant un effacement. Le défaut est `EncryptedExportArchive`.

| Méthode | Contrat |
|---|---|
| `put(string $reference, string $content, DateTimeImmutable $expiresAt)` | Garde le document, hors des journaux. |
| `get(string $reference)` | Le document, ou `null`. |
| `purgeExpired(DateTimeImmutable $now)` | Retire ce qui a expiré, et dit combien. |

## `ConsentProofs`

`record(ConsentChoice $choice)` garde la preuve datée d'un choix. L'enregistrement ne contient ni adresse ni rien sur la
personne. Nommez-en un autre avec `consent.proofs`.

## `CurrentDataSubject` et `DataSubjectUser`

`CurrentDataSubject::current()` est le membre de la requête courante, ou `null`. Le défaut lit l'utilisateur connecté,
dont la classe implémente `DataSubjectUser::dataSubjectIdentifier()` : un identifiant stable, jamais une adresse
courriel. Nommez-en un autre avec `pages.current_subject`.

## `ExportReadme`

`render(array $modules, DateTimeImmutable $madeAt): string` écrit le `README.txt` d'une archive d'export, d'après le
nombre d'entrées de premier niveau du fichier de chaque module. Le défaut est `TwigExportReadme`, sur
`@PhilPrivacy/export/README.txt.twig`, ou `PlainExportReadme` sans Twig. Faites d'un autre service un alias de
l'interface.

## `GuestRequestStore`

Garde les demandes faites sans compte tant que leur lien est valide. La table du bundle est le défaut ; nommez-en un
autre avec `guest_requests.store`.

| Méthode | Contrat |
|---|---|
| `save(GuestRequest $request)` | Ajoute la demande. Garde l'adresse hors de portée d'une copie du stockage. |
| `find(string $id)` | La demande, ou `null`, aussi quand elle ne peut pas être lue. |
| `delete(string $id)` | La retire ; rien quand elle n'est plus là. |
| `purgeExpired(DateTimeImmutable $now)` | Retire celles dont l'expiration est venue, et dit combien. |

## `GuestLinkSender`

`send(GuestRequest $request, string $token)` envoie le lien d'une demande à `$request->email`, construit à partir de la
route `phil_privacy_guest_confirm` avec les paramètres `id` et `token`. Le jeton est le secret du lien : envoyez-le tout
de suite, jamais par une file de messages, et ne l'écrivez jamais dans un journal. Le défaut est `MailerGuestLinkSender`
; nommez-en un autre avec `guest_requests.sender`.

## `GuestSubjects`

`subjectOf(string $email): DataSubject` dit sur quel identifiant les modules indexent les enregistrements d'une adresse
vérifiée. Le défaut, `EmailGuestSubjects`, est l'adresse elle-même. Nommez-en un autre avec `guest_requests.subjects`.

## `IncidentRegister`

Le registre des incidents de confidentialité. La table du bundle est le défaut ; nommez-en un autre avec
`incidents.store`.

| Méthode | Contrat |
|---|---|
| `save(Incident $incident)` | Ajoute la ligne, ou remplace la ligne du même identifiant. |
| `find(string $id)` | La ligne, ou `null`. |
| `all()` | Chaque ligne, celle découverte le plus récemment en premier. |

## `EmailTrackingStore`

L'endroit où est gardé le choix de chaque membre sur les courriels suivis, quand `email_tracking.enabled`. La table du
bundle est le défaut ; nommez-en un autre avec `email_tracking.store`. Voir [email-tracking.md](email-tracking.md).

| Méthode | Contrat |
|---|---|
| `find(DataSubject $subject)` | Le dernier choix du membre, ou `null` s'il n'en a jamais fait. |
| `save(EmailTrackingChoice $choice)` | Garde le choix à la place du précédent du membre ; un choix qui refuse les deux est gardé aussi. |
| `forget(DataSubject $subject)` | Retire le choix du membre ; idempotent, puisqu'un effacement est rejoué. |
| `store()` | Le nom du stockage, tel que le nomme le rapport d'effacement du module `email_tracking`. |

## `Anonymizer`

Une colonne dont les valeurs ne vont à aucun type intégré — un numéro de TVA, une plaque d'immatriculation, une
référence interne à chiffre de contrôle — prend un anonymiseur de l'application :

```php
use Phil\PrivacyBundle\Domain\Anonymization\AnonymizationContext;
use Phil\PrivacyBundle\Domain\Attribute\AsAnonymizer;
use Phil\PrivacyBundle\Domain\Contract\Anonymizer;

#[AsAnonymizer('vat_number')]
final class VatNumberAnonymizer implements Anonymizer
{
    public function anonymize(string|int|float|bool $value, AnonymizationContext $context): string
    {
        return $context->fill($context->options->string('prefix', 'CA').'#########');
    }
}
```

Avec l'autoconfiguration, `#[AsAnonymizer]` étiquette le service `phil_privacy.anonymizer` avec son type, et
`#[Anonymize('vat_number')]` ou `tables: { invoice: { vat: vat_number } }` le nomme. Un type auquel le bundle répond
déjà, comme `email`, est remplacé par l'anonymiseur de l'application.

Les invariants que garde un anonymiseur :

- **Il ne reçoit que des valeurs non nulles**, une ligne à la fois ; l'exécution laisse une valeur nulle à nul sans le
  lui demander.
- **Il lit ses options par `$context->options`**, dont `string()`, `int()` et `scalar()` refusent une option manquante
  ou mal typée en la nommant. Il lève `InvalidPrivacyValue` pour une option ou une valeur qu'il ne sait pas lire :
  l'exécution est une transaction, annulée.
- **Il tire de `$context->random`**, ou par `pick()` et `fill()`, jamais de `random_int()` ni de `rand()`, pour que
  `--seed` rende une exécution reproductible. Une valeur dérivée de la valeur d'origine passe par `$context->digest()`,
  sous le sel de l'exécution.
- **Il rend une valeur que la colonne accepte** : une chaîne pour une colonne de texte, `null` seulement pour une
  colonne nullable.

## `LocalePack`

Une locale que le bundle ne livre pas, ou un paquet des noms propres à l'application, est un service `LocalePack` ; avec
l'autoconfiguration, il est étiqueté `phil_privacy.locale_pack` et rejoint les paquets intégrés, en remplaçant celui du
même code :

```yaml
services:
    app.locale_pack.fr_be:
        class: Phil\PrivacyBundle\Domain\Anonymization\Locale\LocalePack
        arguments:
            - fr_BE
            - [Lucie, Louis, Emma, Arthur]
            - [Peeters, Janssens, Maes, Dubois]
            - [de la Loi, Royale, Neuve, du Midi]
            - ['{street} {number}']
            - [Bruxelles, Liège, Namur, Charleroi]
            - [Wallonie, Bruxelles-Capitale]
            - ['####']
            - []                       # no fictional telephone range: the phone anonymizer needs a pattern
            - BE
            - Belgique
            - '{line}, {postal_code} {city}'
```

Les motifs téléphoniques doivent être des plages que le régulateur du pays garde hors service ; laissez la liste vide
plutôt que d'en inventer une.

## `AnonymizationTarget`

Le port par lequel écrit l'anonymisation, implémenté sur une connexion Doctrine DBAL. Une `AnonymizationTarget` donne la
clé primaire de chaque table (`AnonymizationRefused::withoutKey()` quand il n'y en a pas), chaque ligne avec la clé et
les colonnes demandées indexées par les noms tels que donnés, écrit les valeurs d'une ligne par sa clé, et fait le
travail en une seule transaction.

## Les services et les étiquettes sur lesquels compter

| Identifiant ou étiquette | Défini par | Notes |
|---|---|---|
| `Phil\PrivacyBundle\Infrastructure\Monolog\RedactingProcessor` | le bundle | Service privé ; peut être référencé, décoré ou réétiqueté depuis une passe de compilation. |
| `monolog.processor` | MonologBundle | L'étiquette que le bundle met sur le processeur. |
| `phil_privacy.exporter`, `phil_privacy.eraser` | le bundle | Données par l'autoconfiguration à chaque service qui implémente `PersonalDataExporter` ou `PersonalDataEraser` (`PhilPrivacyBundle::EXPORTER_TAG`, `ERASER_TAG`). Ne les ajoutez pas à la main à un service autoconfiguré : le module serait compté deux fois, et `MisconfiguredPrivacy::duplicateModule()` levée. |
| `Phil\PrivacyBundle\Application\SubjectExport`, `SubjectErasure` | le bundle | Services publics sur ces étiquettes. |
| `Phil\PrivacyBundle\Application\Register\ProcessingRegister` | le bundle | Service public sur `retention_schedule_source`, s'il y en a une. |
| `phil:privacy:export`, `phil:privacy:erase`, `phil:privacy:register` | le bundle | Commandes, enregistrées avec Symfony Console. |
| `phil_privacy.anonymizer` | le bundle | Donnée par `#[AsAnonymizer]` avec son attribut `type` (`PhilPrivacyBundle::ANONYMIZER_TAG`). |
| `phil_privacy.locale_pack` | le bundle | Donnée par l'autoconfiguration à chaque service `LocalePack` (`PhilPrivacyBundle::LOCALE_PACK_TAG`). |
| `Phil\PrivacyBundle\Application\Anonymization\AnonymizationRun` | le bundle | Service public sur les anonymiseurs et les paquets. |
| `phil_privacy.database_identity` | le bundle | `DatabaseIdentity` privée de `database.connection`, que lit la garde de production (`PhilPrivacyBundle::DATABASE_IDENTITY`). |
| `phil:privacy:anonymize` | le bundle | Commande, enregistrée avec Console et Doctrine DBAL, quand la connexion est un service. |
| `phil_privacy.retention_purger` | le bundle | Donnée par l'autoconfiguration à chaque service `RetentionPurger` (`RightsServices::PURGER_TAG`). |
| `Phil\PrivacyBundle\Application\RetentionRun`, `RetentionJob` | le bundle | Services publics, avec un calendrier. |
| `phil:privacy:retention`, `phil:privacy:deletions`, `phil:privacy:incident`, `phil:privacy:incidents` | le bundle | Commandes, enregistrées avec Symfony Console. |
| `Phil\PrivacyBundle\Domain\Cookie\CookieInventory` | le bundle | Service public : l'inventaire de `cookies.inventory`, avec le témoin du consentement. |
| `Phil\PrivacyBundle\Application\Export\ExportPackage` | le bundle | Service public : l'archive d'une réponse d'accès. |
| `Phil\PrivacyBundle\Application\Contract\IncidentRegister` | le bundle | Alias public du registre des incidents. |
| `Phil\PrivacyBundle\Application\GuestRequest\GuestRequests` | le bundle | Service public, quand `guest_requests.enabled`. |
| `Phil\PrivacyBundle\Application\EmailTracking\EmailTrackingConsents` | le bundle | Service public, quand `email_tracking.enabled` : ce que demande l'expéditeur d'un courriel avant de compter les ouvertures ou de suivre les clics d'un membre. |
| `phil_privacy.guest_requests.per_client`, `phil_privacy.guest_requests.per_address` | le bundle | Les deux fabriques de limiteurs de débit des demandes sans compte (`GuestRequestServices::PER_CLIENT`, `PER_ADDRESS`). |
| `Phil\PrivacyBundle\Infrastructure\Testing\AssertsDeclaredCookies` | le bundle | Le trait PHPUnit qui échoue sur un témoin non déclaré. |
| `workflow.phil_privacy_account_deletion.*` | le bundle | Les événements de la suppression de compte ; voir [account-deletion.md](account-deletion.md). |
| `phil_privacy` | le bundle | Le calendrier, `PrivacySchedule::NAME`. |
| `@PhilPrivacy/` | le bundle | Les gabarits, remplacés dans `templates/bundles/PhilPrivacy/`. |

Un exportateur, un effaceur ou un purgeur participe donc dès qu'il est un service avec l'autoconfiguration active ; un
service enregistré sans elle est étiqueté à la main, une fois.

## Autres surfaces d'extension

- **Votre propre composition de caviardeurs.** `ContextRedactor`, `UrlRedactor`, `CommandLineRedactor` et `Denylist`
  sont publics, finaux, et utilisables hors de Monolog — pour le crochet `before_send` d'un traqueur d'erreurs, par
  exemple.
- **Attraper les erreurs du bundle.** Tout ce que lève le bundle implémente
  `Phil\PrivacyBundle\Domain\Exception\PrivacyError`, en gardant son type SPL (`InvalidArgumentException`,
  `LogicException`, `RuntimeException`).
