<!-- phil-dev source-sha256=a120af01963d3f65240080d3251a8caa4f0a02e0bfb798852c8672fd369aa935 -->
# Export et effacement d'une personne

**État :** `SubjectExport`, `SubjectErasure`, les objets valeur et les exceptions sont implémentés et testés.
`PersonalDataExporter` et `PersonalDataEraser` sont des **ports** : l'application les implémente. **Le bundle branche
le reste** : il autoconfigure les deux ports avec les étiquettes `phil_privacy.exporter` et `phil_privacy.eraser`,
enregistre `SubjectExport` et `SubjectErasure` comme services publics sur ces étiquettes, ainsi que les commandes
`phil:privacy:export` et `phil:privacy:erase`.

Le modèle est un contrat par module. Chaque partie de l'application qui détient des renseignements personnels répond de
ses propres enregistrements ; deux services d'application assemblent les réponses pour une personne.

## La personne : `DataSubject`

`DataSubject::fromString(string $value)` — l'identifiant opaque sur lequel chaque module indexe ses enregistrements (un
UUID, un `sub` OIDC, un numéro de client). Le bundle ne l'analyse jamais. Il refuse seulement ce sur quoi aucun module
ne peut indexer :

- une chaîne vide ;
- plus de 255 octets (`DataSubject::MAX_LENGTH`) ;
- tout espace ou caractère de contrôle — c'est ainsi qu'un identifiant copié d'un courriel arrive avec un saut de ligne
  final et ne correspond à rien.

`$value`, `equals(DataSubject $other): bool`, et `Stringable` (`(string) $subject`).

## Le module : `ModuleName`

`ModuleName::fromString(string $value)` — la clé de la section d'un module dans un export, et le nom auquel son rapport
d'effacement et son échec sont attribués. Une lettre minuscule d'abord, puis des lettres minuscules, des chiffres, `_`,
`.` ou `-` ; au plus 64 caractères (`billing`, `crm.contacts`).

## Export

### Port : `PersonalDataExporter`

```php
public function module(): ModuleName;

/** @return array<string, mixed> JSON-encodable; [] when the module holds nothing on the person */
public function export(DataSubject $subject): array;
```

Construisez l'export **à partir des modèles de lecture qu'interroge déjà le module**, pour qu'il reste juste à mesure
que le modèle change, au lieu de devenir une seconde vue tenue à la main.

### Service : `SubjectExport`

```php
$export = new SubjectExport([$billingExporter, $crmExporter]);
$sections = $export->export(DataSubject::fromString($userId));
// ['billing' => [...], 'crm' => [...]]
```

- `__construct(iterable<PersonalDataExporter> $exporters)` — lève `MisconfiguredPrivacy::duplicateModule()` quand deux
  exportateurs partagent un nom de module.
- `export(DataSubject $subject): array<string, array<string, mixed>>` — une section par module, **triée par nom de
  module** (`ksort`, ordre des chaînes), pour que deux exports des mêmes données soient le même document. Un module qui
  ne détient rien a tout de même sa section, vide : la réponse montre que le module a été interrogé. Sans exportateur,
  le résultat est `[]`.
- `export(DataSubject $subject, HeldLock $lock)` renouvelle le verrou avant chaque module, et lève `LockLost` avant le
  module suivant dès qu'il est perdu ([Verrous et longs travaux](#verrous-et-longs-travaux)).

L'encodage (JSON, CSV, archive), la livraison, la vérification de l'identité et les délais reviennent à l'application.

## Effacement

### Port : `PersonalDataEraser`

```php
public function module(): ModuleName;

public function erase(DataSubject $subject): ErasureReport;
```

**Doit être idempotent.** Un effacement qui échoue est relancé, et un effacement est rejoué après la restauration d'une
sauvegarde : le relancer sur une personne déjà effacée doit réussir et rapporter les mêmes effets.

### Valeur : `ErasureReport`

`ErasureReport::of(string $module, array<string, ErasureEffect> $effects)` — ce qu'a fait un module, stockage par
stockage.

- `$module` (`ModuleName`) et `$effects` (par nom de stockage).
- **Un rapport vide est refusé** : on ne peut pas le distinguer d'un module qui a oublié un stockage. Un module qui n'a
  rien effacé rapporte tout de même chaque stockage avec `Survives` ou `AwaitsExpiry` quand c'est la réponse.
- Un nom de stockage vide est refusé.

Les cas de `ErasureEffect` : `Deleted`, `Pseudonymised` (l'enregistrement survit sans rien qui nomme la personne),
`Redacted` (certains champs vidés ou remplacés), `AwaitsExpiry` (rien n'est retiré maintenant ; la durée de conservation
arrive à son terme), `Survives` (gardé exprès, sous une obligation légale ou parce que le retirer déferait la demande).

### Service : `SubjectErasure`

```php
$erasure = new SubjectErasure([$billingEraser, $crmEraser]);

try {
    $reports = $erasure->erase($subject);          // list<ErasureReport>, sorted by module name
} catch (ErasureIncomplete $incomplete) {
    $incomplete->completed;                        // list<ErasureReport> of the modules that succeeded
    $incomplete->failures;                         // array<string, Throwable> by module name
    $incomplete->getPrevious();                    // the first failure
    // record, alert, retry later: every eraser runs again, which is why they are idempotent
}
```

- `__construct(iterable<PersonalDataEraser> $erasers)` — lève `MisconfiguredPrivacy::duplicateModule()` sur un nom de
  module en double. Les effaceurs s'exécutent dans l'ordre des noms de module.
- **Un module en échec n'arrête pas les autres.** Chaque effaceur s'exécute ; si l'un a échoué, `ErasureIncomplete`
  porte ce qui a réussi et ce qui n'a pas réussi, pour qu'on ne dise jamais à une personne qu'un effacement est terminé
  alors qu'il ne l'est pas. S'arrêter au premier échec laisserait les modules suivants intacts sans raison.
- **Un rapport classé sous le nom d'un autre module est un échec** de l'effaceur qui l'a rendu
  (`UnexpectedValueException` dans `$failures`) : sinon, il marquerait comme fait un module qui ne s'est jamais exécuté.
- Tout `Throwable` venu d'un effaceur est attrapé et noté, erreurs comprises.
- `erase(DataSubject $subject, HeldLock $lock)` renouvelle le verrou avant chaque effaceur. Un verrou perdu lève
  `LockLost` avant l'effaceur suivant ; il n'est jamais noté comme l'échec d'un module
  ([Verrous et longs travaux](#verrous-et-longs-travaux)).

Message de `ErasureIncomplete` : `Erasure failed in module(s) <names>; retry it, erasers are idempotent.`

## Verrous et longs travaux

Le travail que le bundle exécute un à la fois — un export, un effacement, une exécution de conservation — tient un
verrou de `symfony/lock` dont le bail expire : si le processus se fige ou meurt, le suivant n'est pas bloqué pour
toujours. Un travail plus long que le bail laisserait un second *worker* prendre le verrou expiré et exécuter en même
temps le même export, le même effacement ou la même purge ; le verrou est donc renouvelé entre les étapes, plutôt que
dimensionné pour le travail entier.

`HeldLock` enveloppe le verrou. `renew()` repousse son expiration de sa durée de vie, puis demande au stockage s'il est
toujours celui de ce processus ; quand le stockage dit non — le bail a expiré, un autre processus l'a pris, ou le
stockage ne peut pas répondre —, il lève `LockLost`. `release()` l'abandonne une fois, et ne fait rien une fois qu'il
est perdu.

| Travail | Verrou | Durée de vie | Renouvelé avant |
|---|---|---|---|
| `GuestRequests::access()`, `erase()` | `phil_privacy.guest.<request>` | `GuestRequests::LOCK_TTL`, 300 s | chaque module, le retrait de la demande |
| `AccountDeletions::advance()` | `phil_privacy.account_deletion.<hash>` | `AccountDeletions::LOCK_TTL`, 900 s | chaque module, l'archive, chaque place enregistrée |
| `PersonalDataDownload::of()` | `phil_privacy.export.<hash>` | `PersonalDataDownload::LOCK_TTL`, 300 s | chaque module |
| `RetentionJob::run()` | `phil_privacy.retention` | `RetentionJob::LOCK_TTL`, 3 600 s | chaque ordre de purge |

Un travail dont le verrou a été perdu s'arrête avant son étape suivante, journalise un avertissement qui nomme la
demande et jamais la personne, et garde ce qu'il a enregistré : une demande sans compte reste et son lien la relance, une
suppression de compte reste à sa dernière place, un téléchargement répond comme quand un autre export de la personne
s'exécute, une exécution de conservation répond comme quand une autre exécution tient le verrou. Le *worker* qui tient le
verrou maintenant fait le reste ; chaque étape se rejoue sans risque, parce que les exportateurs lisent, et que les
effaceurs et les purgeurs sont idempotents.

### Exploitation

- **Chaque étape doit finir dans la durée de vie.** Le travail entier peut durer plus longtemps, une étape non : un
  exportateur ou un effaceur d'une personne, un ordre de purge, ou l'écriture de l'export dans son archive. Un purgeur
  qui retire une grande table purge par lots dans l'ordre, pour que l'ordre finisse bien en dessous de l'heure ; un
  module dont l'export ou l'effacement d'une personne peut prendre des minutes est mesuré, et scinde son travail s'il
  approche cinq minutes.
- **Le stockage doit faire expirer les baux.** Un stockage partagé — Redis, PDO, PostgreSQL — laisse le *worker* suivant
  prendre un verrou expiré ; le stockage `flock` par défaut ne sert qu'un seul serveur. Un stockage qui échoue est
  traité comme un verrou perdu : le travail s'arrête et l'exécution suivante le fait.
- **Un arrêt est un avertissement, pas une erreur.** `Request without an account {id}: its lock was lost; the request is
  kept.`, `Account deletion {id}: its lock was lost; the stage stopped.` et `The retention run lost its lock and stopped
  before its next rule.` signifient qu'une étape a dépassé la durée de vie ou que le stockage s'est figé ; des messages
  fréquents appellent une mesure de l'étape lente.

## Le branchement dans Symfony

Un service qui implémente `PersonalDataExporter` est étiqueté `phil_privacy.exporter`, un qui implémente
`PersonalDataEraser` `phil_privacy.eraser`, par l'autoconfiguration ; `SubjectExport` et `SubjectErasure` prennent
chaque module étiqueté et sont publics. Les modules qui participent sont décidés par les adaptateurs qu'enregistre
l'application. Les noms de module en double se révèlent à la première instanciation du service : couvrez donc chaque
service par un test qui le construit depuis le conteneur.

Le bundle participe comme un module à lui quand `email_tracking.enabled` : `email_tracking` exporte le choix du membre
sur les courriels suivis — sa version, `opens`, `clicks` et sa date en UTC —, et son effacement le supprime
([email-tracking.md](email-tracking.md)).

## Depuis la console

```bash
bin/console phil:privacy:export 0192f3c4-… --output=answer.json
bin/console phil:privacy:erase 0192f3c4-… --confirm
```

`phil:privacy:export` affiche la réponse à une demande d'accès en JSON, une section par module, ou l'écrit dans
`--output`. Le fichier contient chaque enregistrement personnel de la personne : il est créé lisible et modifiable par
son propriétaire seulement (`0600`), quel que soit l'umask ; un fichier existant est refusé sauf avec `--force`, et
ramené lui aussi à `0600`. Une écriture qui échoue ou reste incomplète se termine en échec et le dit, jamais `The answer
… is in` sur un fichier vide ou absent. `phil:privacy:erase` ne fait rien sans `--confirm` ; avec, elle affiche ce qu'a
fait chaque stockage de chaque module — `module: store, effect` —, et quand un module échoue, les modules qui ont
terminé et celui qui a échoué avec la classe de son exception — `module: failed, class` —, et se termine en échec : la
relancer recommence, les effaceurs étant idempotents. Le message de l'exception, qui peut citer les données de la
personne, n'est affiché qu'avec `-v`. Un identifiant de personne que le domaine refuse se termine comme invalide.

## Le registre des traitements

```bash
bin/console phil:privacy:register --format=markdown > docs/register-of-processing.md
bin/console phil:privacy:register --strict          # fails while the register has gaps, for the CI
```

Le registre a deux parties, et nomme ce qui l'empêche d'être complet.

- **Ses activités** sont les règles du calendrier de conservation qui détiennent des renseignements personnels, ou
  qu'un champ nomme, dans l'ordre du calendrier : la règle, son stockage et ses catégories, sa finalité, ses
  destinataires, ses sous-traitants et son lieu ([retention.md](retention.md#traitement-et-sous-traitant)), sa durée, et
  les champs `#[PersonalData]` qui la nomment. Sans calendrier, il n'y en a aucune.
- **Ses champs** sont chaque `#[PersonalData]` des classes sous `phil_privacy.register.classes` et de chaque entité
  Doctrine, une ligne par champ — un champ déclaré sur un parent une seule fois, sous sa classe —, ordonnés par
  catégorie, classe et propriété : la catégorie, la règle de conservation qu'il nomme et, avec un calendrier, le
  stockage, la durée, le mécanisme et la réponse à l'effacement de cette règle. Un champ dont le calendrier ne contient
  pas la règle montre le seul nom de la règle.
- **Ses lacunes**, une phrase chacune : une activité qui ne dit pas sa finalité, une qui ne dit pas son lieu et, avec un
  calendrier, un champ qui nomme une règle que le calendrier ne contient pas.

`ProcessingRegister::activities()`, `entries()` et `gaps()` donnent les trois parties ; chacune accepte tout itérable de
noms de classes, un générateur compris. La sortie Markdown a une section par partie, celle des lacunes seulement s'il y
en a ; une cellule garde ses barres verticales échappées et ses lignes jointes, pour qu'une finalité ne puisse pas
casser le tableau. `--format=json` donne un objet avec `activities`, `fields` et `gaps`. Avec `--strict`, la commande se
termine en échec tant qu'il reste des lacunes, et dit combien sur la sortie d'erreur.

## L'archive d'une réponse d'accès

L'export d'une personne est une seule archive ZIP : un `README.txt` qui dit ce qu'elle est, quand elle a été faite et ce
que contient chaque fichier, puis un fichier JSON par module, `<module>.json`, dans l'ordre des modules. Un module qui
ne garde rien sur la personne est un objet vide, et le README le dit. L'archive est écrite par `ZipFile`, sans
l'extension zip : deflate brut quand zlib est là, stocké sinon, noms en UTF-8. Il refuse un nom qui pourrait sortir du
dossier où on l'ouvre — un chemin absolu, `..`, `.`, une barre oblique inverse, un caractère de contrôle —, un nom donné
deux fois, et ce que le format simple ne peut pas tenir, au-delà de 65 535 fichiers ou de 4 Gio.

```php
$zip = $container->get(ExportPackage::class)->zip($subjectExport->export($subject));
```

Le README vient d'un `ExportReadme` : avec Twig, `@PhilPrivacy/export/README.txt.twig` dans la langue de la requête, ses
textes dans le domaine `phil_privacy` ; sans Twig, `PlainExportReadme`, en anglais. Remplacez le gabarit pour parler de
votre site et de la personne à joindre.

Le téléchargement d'un membre depuis ses préférences, la réponse à une demande faite sans compte
([requests-without-an-account.md](requests-without-an-account.md)) et l'export écrit avant l'effacement d'un compte sont
tous cette archive.

## Ce qui revient à l'application

La façon de recevoir et de noter une demande qui ne passe pas par le site ; les délais légaux ; le registre des demandes
; et ce qu'efface chaque module, et comment. Les demandes des personnes sans compte qui écrivent par le site reviennent
au bundle, une fois activées.
