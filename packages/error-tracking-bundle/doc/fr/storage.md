<!-- phil-dev source-sha256=b4fefeda17ee2d6da53179d5dd3d3ed52a9c0b1798258b9a08a29314ebfa93e1 -->
# Stockage

## Les tables

`phil_error_group`, une ligne par empreinte :

| Colonne | Type |
|---|---|
| `fingerprint` | 64 caractères, clé primaire |
| `title`, `culprit` | texte, le coupable pouvant être nul |
| `status` | `open`, `resolved`, `ignored` ou `regressed` |
| `times_seen` | grand entier |
| `first_seen_at`, `last_seen_at` | date et heure en UTC |
| `last_release`, `resolved_release` | 128 caractères, pouvant être nuls |

`phil_error_event`, les événements assainis de chaque groupe :

| Colonne | Type |
|---|---|
| `id` | grand entier, dans l'ordre d'arrivée des événements |
| `event_id` | les 32 chiffres hexadécimaux de l'événement |
| `fingerprint` | son groupe |
| `occurred_at` | date et heure en UTC |
| `payload` | l'événement en JSON, tiré d'`ErrorEvent::toArray()` |

Les instants sont des colonnes du type `datetime_utc_immutable` de DBAL : écrits en UTC et relus en UTC, quel que soit
le fuseau horaire de PHP ou de la base de données, pour que deux serveurs de fuseaux différents lisent le même moment
et que la purge compare des choses comparables.

Avec l'ORM, `SchemaListener` ajoute les deux tables au schéma de leur connexion, pour que `doctrine:migrations:diff`
écrive leur migration. Sans lui :

```php
use Doctrine\DBAL\Schema\Schema;
use Phil\ErrorTrackingBundle\Infrastructure\Doctrine\ErrorTables;

foreach (ErrorTables::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $sql) {
    $connection->executeStatement($sql);
}
```

## Le plafond d'événements

Chaque événement enregistré est ajouté, puis les événements de son groupe au-delà de `storage.events_per_group` sont
supprimés, les plus anciens d'abord, en une seule instruction. Le groupe continue de compter chaque événement, gardé
ou non.

## Concurrence

Un groupe est mis à jour quand il a une ligne, et inséré sinon ; quand deux premiers événements arrivent en même temps,
la seconde insertion rencontre la clé unique et fait plutôt une mise à jour. Deux processus qui lisent un groupe en
même temps et l'enregistrent l'un après l'autre perdent un compte : le compte est un ordre de grandeur, pas un grand
livre.

## La purge

```sh
bin/console phil:error-tracking:purge                  # the retention of the configuration
bin/console phil:error-tracking:purge --older-than=7   # days
```

La commande retire les événements plus anciens que la durée de conservation et les groupes qui n'ont pas été vus
depuis, et affiche le nombre de lignes retirées. Lancez-la chaque nuit, depuis le planificateur ou un cron.
