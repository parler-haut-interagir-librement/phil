<!-- phil-dev source-sha256=745906f619061ee8c7c63c112218c55752dd7c0d176929b80a21febca2a26711 -->
# Calendrier de conservation et purges

Le calendrier vient de la configuration ou d'une `RetentionScheduleSource` de l'application. Les adaptateurs
`RetentionPurger` sont trouvés par l'autoconfiguration. Le bundle exécute la tâche : une commande, un message et un
calendrier d'exécution.

```yaml
phil_privacy:
    retention:
        frequency: '1 day'
        rules:
            R-1: { store: trash, period: P30D }
            R-2: { store: phil_privacy_account_deletion, period: P1Y }
            R-3: { store: phil_privacy_consent_proof, period: P3Y }
            R-4:
                store: accounts
                categories: [email, person_name]
                period: { until_event: 'the account is closed' }
                mechanism: event
                purpose: Running the members' accounts
                recipients: [Membership team]
                processors:
                    - { name: Hosting provider, location: 'Canada (Montréal)' }
                    - { name: Mail service, location: United States }
                location: 'Canada (Montréal)'
```

Une ligne a un `store`, ses `categories`, une `period` (une durée ISO 8601, `indefinite`, `{ until_event: … }` ou
`{ not_yet_fixed: … }`), et `mechanism` (`job` par défaut), `erasure` (`deleted` par défaut) et `access` (`returned` par
défaut). Les clés `purpose`, `recipients`, `processors` et `location` sont ce que dit du stockage le registre des
traitements ([export-and-erasure.md](export-and-erasure.md#le-registre-des-traitements)) : le calendrier déclare le
registre. `retention_schedule_source` nomme plutôt une source : donner les deux arrête la construction, un calendrier
n'ayant qu'une autorité.

Purger une sauvegarde expirée revient à `phil/backup-bundle`, et l'expiration d'un contenu publié à
`phil/content-expiry`.

Un calendrier de conservation est un contrôle, pas un document : chaque ligne nomme ce qui le fait respecter, et les
lignes qu'une tâche fait respecter deviennent des ordres de purge qui doivent atteindre un purgeur.

## `RetentionPeriod`

Combien de temps un stockage garde un enregistrement. Quatre formes, parce qu'un calendrier contient les quatre et
qu'une seule est un nombre.

| Fabrique | Genre (`RetentionPeriodKind`) | `$duration` | `$note` | Date limite |
|---|---|---|---|---|
| `fixed('P24M')` | `Fixed` | la durée ISO 8601 | `null` | calculée |
| `untilEvent('account closure')` | `UntilEvent` | `null` | l'événement | `null` |
| `indefinite()` | `Indefinite` | `null` | `null` | `null` |
| `notYetFixed('legal review')` | `NotYetFixed` | `null` | où la valeur sera décidée | `null` |

- `fixed()` accepte la forme ISO 8601 à désignateurs `P[nY][nM][nW][nD][T[nH][nM][nS]]`. La forme alternative
  (`P0001-00-00`) et une durée nulle (`P0D`) sont refusées — un enregistrement gardé pendant aucun temps n'est pas
  stocké.
- `untilEvent()` et `notYetFixed()` refusent une note vide.
- `isFixed(): bool`.
- `cutoff(DateTimeImmutable $now): ?DateTimeImmutable` — l'instant avant lequel un enregistrement a dépassé la durée, ou
  `null` quand la durée n'est pas un nombre. Une durée « pas encore fixée » est un blanc compté qui ne produit
  **jamais** de date limite.
- `lastsAtLeast(RetentionPeriod $minimum): bool` — si la durée ne purge jamais un enregistrement avant que `$minimum` le
  ferait : une durée qui n'est pas un nombre ne purge jamais, et deux durées se comparent par leurs dates limites à
  partir de fins de mois et des jours bissextiles d'un cycle de quatre ans, de sorte que `P60M` dure au moins autant que
  `P5Y` et `P1826D` non. La construction s'en sert pour refuser une règle de moins de cinq ans sur le registre des
  incidents ([incidents.md](incidents.md#le-garder)).

**Les dates limites sont calculées en UTC, et le calcul des mois ramène à la fin du mois.** PHP change le 31 mars moins
un mois en 3 mars ; une purge sur cette date limite efface trois jours trop tôt. Ici, c'est le 28 février (le 29 une
année bissextile), et le 29 février moins un an est le 28 février. Les années et les mois sont soustraits d'abord,
ramenés à la fin du mois, puis le reste de l'intervalle. Un enregistrement n'est jamais purgé avant que sa durée soit
entièrement écoulée.

```php
RetentionPeriod::fixed('P1M')->cutoff(new DateTimeImmutable('2026-03-31T10:00:00-04:00'));
// 2026-02-28T14:00:00+00:00
```

## `RetentionRule`

Une ligne du calendrier.

```php
new RetentionRule(
    id: 'R-002',
    store: 'crm.contacts',
    categories: [DataCategory::fromString('email'), DataCategory::fromString('person_name')],
    period: RetentionPeriod::fixed('P24M'),
    mechanism: Mechanism::Job,
    erasure: ErasureEffect::Deleted,
    access: AccessResponse::Returned,
    processing: new Processing(
        purpose: 'Answering the people who write in',
        recipients: ['Support team'],
        processors: [new Processor('Mail service', 'United States')],
        location: 'Canada',
    ),
);
```

| Champ | Règle |
|---|---|
| `id` | `RetentionRule::ID_PATTERN` : une lettre ou un chiffre d'abord, puis des lettres, des chiffres, `.`, `_`, `-` ; au plus 64 caractères. Le vôtre ; ajouté et jamais renuméroté, parce que les déclarations `#[PersonalData]` le citent. |
| `store` | Non vide. Le nom dont répond un `RetentionPurger`. |
| `categories` | `list<DataCategory>`, sans doublon. Vide, elle dit que le stockage ne détient aucun renseignement personnel (`holdsPersonalInformation()` vaut `false`). |
| `period` | Une `RetentionPeriod`. |
| `mechanism` | `Mechanism` : `Job` (une purge que ce bundle mène par un `RetentionPurger`), `Ttl` (le stockage fait expirer ses propres entrées), `Rotation` (rotation des journaux ou des fichiers), `Event` (une action ou un événement du domaine le retire), `Manual` (une personne purge à une cadence notée). |
| `erasure` | `ErasureEffect` : ce que fait à ce stockage l'effacement d'une personne. |
| `access` | `AccessResponse` : `Returned` (champ par champ), `AsCategory` (nommé avec sa durée, enregistrements non extraits : journaux, sauvegardes), `NotReturned`. |
| `processing` | Un `Processing`, vide par défaut : ce que dit du stockage le registre des traitements. |

Refusés à la construction, parce que chacun est une contradiction qu'un lecteur devrait sinon remarquer :

- **une tâche de purge sur une durée indéfinie** (`Mechanism::Job` avec `indefinite()`) : la tâche ne pourrait jamais
  purger ;
- **`AwaitsExpiry` sans durée fixe** : la réponse à une demande d'effacement est la durée, et il n'y en a pas.

## `Processing` et `Processor`

Ce que dit d'un stockage le registre des traitements, à côté du temps qu'il garde un enregistrement.

| Champ | Règle |
|---|---|
| `purpose` | Pourquoi le stockage détient les enregistrements : de 1 à 1 000 caractères, ou `null`. |
| `recipients` | `list<string>` : qui reçoit les enregistrements — une équipe, un partenaire, une autorité —, une ligne d'au plus 200 caractères chacun, sans doublon. |
| `processors` | `list<Processor>` : les fournisseurs de services qui traitent les enregistrements, chacun un `name` et une `location`, une ligne d'au plus 200 caractères chacun, deux jamais du même nom. |
| `location` | Où le stockage garde les enregistrements — un pays, une région, un centre de données —, une ligne, ou `null`. |

Chaque partie est facultative, pour qu'un calendrier puisse s'écrire avant que son registre soit complet. `isComplete()`
est vrai quand la finalité et le lieu sont donnés, les deux questions que la loi pose toujours d'un stockage.
`Processor::toString()` est `name (location)`.

## `RetentionSchedule`

`new RetentionSchedule(iterable<RetentionRule> $rules)` — refuse un identifiant en double ; garde l'ordre du calendrier.

| Méthode | Renvoie |
|---|---|
| `has(string $id): bool` | si une règle existe |
| `rule(string $id): RetentionRule` | la règle ; lève `InvalidPrivacyValue` si elle est inconnue |
| `rules(): list<RetentionRule>` | chaque règle, dans l'ordre du calendrier |
| `purgeOrders(DateTimeImmutable $now): list<PurgeOrder>` | un ordre par règle `Job` à durée fixe, dans l'ordre du calendrier |
| `jobsWithoutCutoff(): list<RetentionRule>` | les règles `Job` dont la durée va jusqu'à un événement ou n'est pas encore fixée |

Le bundle ne décide pas des lignes d'un calendrier, et ne le lit que dans sa propre configuration ; une source de
l'application lit tout autre fichier.

## `PurgeOrder`

`$ruleId`, `$store`, `$cutoff` (`DateTimeImmutable` en UTC) : retirer ce que couvre la règle et qui est plus ancien que
la date limite.

## `RetentionRun`

Le corps de la tâche de conservation.

```php
$run = new RetentionRun($scheduleSource, [$clicksPurger, $historyPurger]);
$purged = $run->run(new DateTimeImmutable());   // ['R-1' => 5, 'R-2' => 0]
```

- `__construct(RetentionScheduleSource $schedule, iterable<RetentionPurger> $purgers)` — lève
  `MisconfiguredPrivacy::duplicatePurger()` quand deux purgeurs répondent d'un même stockage.
- `orders(DateTimeImmutable $now): list<PurgeOrder>` — ce que purgerait une exécution, ou le refus ci-dessous.
- `run(DateTimeImmutable $now, ?HeldLock $lock = null): array<string, int>` — charge le calendrier, calcule les ordres de
  purge, puis :
  1. **vérifie que chaque ordre a un purgeur avant de purger quoi que ce soit**, et lève sinon
     `MisconfiguredPrivacy::missingPurger($store, $ruleIds)`. Une ligne de tâche sans rien derrière elle est une durée
     annoncée et non respectée ; purger d'abord les autres stockages la rapporterait comme un succès partiel ;
  2. remet chaque ordre au purgeur de son stockage, dans l'ordre du calendrier, en renouvelant le verrou détenu avant
     chacun et en levant `LockLost` avant l'ordre suivant dès qu'il est perdu ;
  3. rend les enregistrements purgés, par identifiant de règle.
- Les lignes qui ne donnent aucune date limite ne sont pas vérifiées et ne reçoivent aucun ordre ; listez-les avec
  `jobsWithoutCutoff()` et exécutez-les ou rapportez-les à votre façon.
- Un purgeur qui lève arrête l'exécution à cet ordre ; les ordres d'avant ont été exécutés.

## L'exécuter

`RetentionJob` est l'exécution comme tâche : à l'instant présent, une à la fois sous un verrou, avec une ligne de
journal par règle qui donne la règle et le compte, jamais ce qui a été purgé. Une seconde exécution pendant qu'une autre
est en cours ne fait rien.

Le verrou dure une heure et est renouvelé avant chaque ordre de purge : une exécution peut donc durer plus longtemps,
pourvu que chaque ordre finisse dans l'heure. Une exécution dont le verrou a expiré et a été pris par une autre s'arrête
avant son ordre suivant, journalise un avertissement et répond `null`, comme une exécution qui a trouvé le verrou pris
([Verrous et longs travaux](export-and-erasure.md#verrous-et-longs-travaux)).

- `bin/console phil:privacy:retention` liste ce que purgerait une exécution, et nomme les lignes de tâche sans date
  limite ; `--confirm` purge. Une ligne de tâche sans purgeur arrête la commande avant que quoi que ce soit soit purgé.
- Le message `RunRetention` la lance de n'importe où.
- Avec `symfony/scheduler`, le calendrier `phil_privacy` envoie ce message chaque `retention.frequency` :
  `bin/console messenger:consume scheduler_phil_privacy`.

Les ports sont décrits dans [extension-points.md](extension-points.md).
