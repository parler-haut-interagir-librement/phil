# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `doctrine/dbal`, `doctrine/orm` | ^4.5, ^3.7 | The table of usages; the flush listener |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | The bundle class and its configuration |
| `phil/resource` | ^0.1 | `ResourceReference`, the kind and identifier of a resource |
| `symfony/console` | optional | `phil:references:check` |

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

The bundle adds `phil_reference_usage` to the schema the ORM generates for its connection — `usage_key`, the primary
key, a SHA-256 of the five other columns; `source_kind`, `source_id`, `field`, `target_kind`, `target_id`; an index on
the target and one on the source —: `doctrine:migrations:diff` writes the migration. Every key stays within the 3072
bytes MySQL's InnoDB allows in `utf8mb4`.
