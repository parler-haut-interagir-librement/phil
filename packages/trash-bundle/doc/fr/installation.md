<!-- phil-dev source-sha256=7ef43f018041aab4c4c801709ee65fd549c814d368c869360becd147995160b9 -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `ext-mbstring` | toute | Les titres comptés en caractères |
| `doctrine/orm` | ^3.7 | La table de la corbeille |
| `psr/clock`, `psr/event-dispatcher` | ^1.0 | Le moment de la mise à la corbeille, par le service `clock` du conteneur, que FrameworkBundle enregistre une fois `symfony/clock` installé ; les événements |
| `symfony/dependency-injection`, `symfony/config`, `symfony/uid` | ^8.2 | La classe du bundle, sa configuration, les identifiants |
| `phil/resource` | ^0.1 | `ResourceReference`, le type et l'identifiant d'une ressource |
| `symfony/console` | facultatif | `phil:trash:purge` |

```sh
composer require phil/trash-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\TrashBundle\PhilTrashBundle::class => ['all' => true],
];
```

```yaml
# config/packages/phil_trash.yaml
phil_trash:
    retention: 30            # days a trashed resource is kept; 30 when left out
    retention_by_kind:
        page: 90             # pages are kept longer
```

Avec DoctrineBundle, le bundle mappe lui-même son entité ; les migrations de l'application créent `phil_trash_item` :
`id`, `resource_key` (64), `resource_id` (255), `title` (255), `payload` (JSON), `parent` (`kind:id`, nullable),
`trashed_at`, `trashed_by` (nullable), `purge_after`, avec des index sur la ressource et sur `purge_after`.

## Collation des clés

**Les clés se comparent octet par octet**, comme PHP les compare. Une ressource mise à la corbeille se trouve, se
restaure et se purge par sa clé telle qu'elle a été écrite : la ressource `Doc-A` n'est jamais restaurée ni purgée pour
`doc-a`.
`BinaryKeysListener` donne à ces champs la collation binaire quand l'ORM charge leurs métadonnées sur MySQL ou
MariaDB ; l'association elle-même reste la même sur toutes les plateformes.

| Table | Colonnes en `utf8mb4_bin` sur MySQL et MariaDB |
|---|---|
| `phil_trash_item` | `id`, `resource_key`, `resource_id`, `parent`, `trashed_by` |

Sur MySQL et MariaDB, ces colonnes prennent le jeu de caractères `utf8mb4` et la collation `utf8mb4_bin`, selon la
plateforme de la connexion : la collation par défaut de MySQL 8, `utf8mb4_0900_ai_ci`, ne distingue ni la casse ni les
accents. Les collations par défaut de PostgreSQL sont déterministes, égales seulement quand les octets le sont, et
SQLite compare les octets : leurs colonnes gardent la valeur par défaut.

### Mise à niveau depuis 0.1

Les tables d'une installation 0.1 sur MySQL ou MariaDB gardaient la collation de la connexion.
`doctrine:migrations:diff` écrit la migration qui amène les colonnes ci-dessus à `utf8mb4_bin` ; exécutez-la avec
`doctrine:migrations:migrate`. Sur PostgreSQL et SQLite, le diff est vide.

Le changement ne fait que distinguer davantage les valeurs : aucune ligne existante ne brise donc une clé unique.
Il sépare ce que la collation insensible à la casse réunissait : les lignes dont les clés ne diffèrent que par la
casse, qu'une recherche par l'une ou l'autre graphie retournait ensemble, se trouvent chacune par leur propre graphie
dès la migration, et une clé que l'application cherche dans une autre casse que celle où elle l'a écrite cesse de
correspondre. Avant de migrer, listez les clés écrites en plus d'une casse :

```sql
SELECT resource_key, resource_id, COUNT(*) AS row_count FROM phil_trash_item
GROUP BY resource_key, resource_id HAVING COUNT(DISTINCT CAST(resource_id AS BINARY)) > 1;
```

Chaque ligne est une clé sous plusieurs graphies. Gardez-les distinctes, ou ramenez les lignes à la graphie
qu'utilise l'application avec un `UPDATE` avant la migration. Une clé unique ne peut contenir deux telles graphies sous
l'ancienne collation, donc les colonnes d'une clé unique n'ont pas besoin de vérification.
