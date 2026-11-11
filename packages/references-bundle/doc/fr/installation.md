<!-- phil-dev source-sha256=92fdb23f0f004ad54bc58cfe543037d96afbf08e6dd97785c4975fa9a1f4bf27 -->
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
