<!-- phil-dev source-sha256=99b29ad0294cf55c76650ff014173b997dae6a95773922f7982d4226142a86d0 -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `doctrine/dbal`, `doctrine/orm` | ^4.5, ^3.7 | La table des usages ; l'écouteur du *flush* |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | La classe du bundle et sa configuration |
| `phil/resource` | ^0.1 | `ResourceReference`, le genre et l'identifiant d'une ressource |
| `symfony/console` | facultatif | `phil:references:check` |

```sh
composer require phil/references-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\ReferencesBundle\PhilReferencesBundle::class => ['all' => true],
];
```

```yaml
# config/packages/phil_references.yaml
phil_references:
    connection: default     # the Doctrine connection of the table, and whose entities are tracked
    track_entities: true    # usages kept up to date at each flush
    block_deletion: true    # deleting an entity still used aborts the flush
```

Le bundle ajoute `phil_reference_usage` au schéma que l'ORM génère pour sa connexion : `usage_key`, la clé primaire,
une empreinte SHA-256 des cinq autres colonnes ; `source_kind`, `source_id`, `field`, `target_kind`, `target_id` ; un
index sur la cible et un sur la source. `doctrine:migrations:diff` écrit la migration. Chaque clé reste sous les
3072 octets que permet InnoDB de MySQL en `utf8mb4`.

## Collation des clés

**Les clés se comparent octet par octet**, comme PHP les compare. Les usages d'une source se lisent et se rafraîchissent
par son genre et son identifiant, ceux d'une cible se retrouvent par le sien : les usages de la source `Doc-A` ne sont
jamais lus, ni oubliés à son rafraîchissement, pour `doc-a`.

| Table | Colonnes en `utf8mb4_bin` sur MySQL et MariaDB |
|---|---|
| `phil_reference_usage` | `usage_key`, `source_kind`, `source_id`, `field`, `target_kind`, `target_id` |

Sur MySQL et MariaDB, ces colonnes prennent le jeu de caractères `utf8mb4` et la collation `utf8mb4_bin`, selon la
plateforme de la connexion : la collation par défaut de MySQL 8, `utf8mb4_0900_ai_ci`, ignore la casse et les accents.
Les collations par défaut de PostgreSQL sont déterministes, égales seulement quand les octets le sont, et SQLite compare
les octets : leurs colonnes gardent la valeur par défaut.

### Mise à niveau depuis 0.1

Les tables d'une installation 0.1 sur MySQL ou MariaDB gardaient la collation de la connexion.
`doctrine:migrations:diff` écrit la migration qui amène les colonnes ci-dessus à `utf8mb4_bin` ; exécutez-la avec
`doctrine:migrations:migrate`. Le changement ne fait que distinguer davantage les valeurs : aucune ligne existante ne
brise une clé. Les lignes qu'une collation insensible à la casse avait déjà fusionnées restent fusionnées ; les valeurs
écrites à partir de la migration sont gardées distinctes. Sur PostgreSQL et SQLite, le diff est vide.
