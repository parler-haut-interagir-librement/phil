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

## Collation of the keys

**Keys compare byte by byte**, as PHP compares them. A source's usages are read and refreshed by its kind and
identifier, a target's found by its own: the usages of the source `Doc-A` are never read, nor forgotten at its
refresh, for `doc-a`.

| Table | Columns in `utf8mb4_bin` on MySQL and MariaDB |
|---|---|
| `phil_reference_usage` | `usage_key`, `source_kind`, `source_id`, `field`, `target_kind`, `target_id` |

On MySQL and MariaDB, these columns take the `utf8mb4` character set and the `utf8mb4_bin` collation, given the
connection's platform: the default collation of MySQL 8, `utf8mb4_0900_ai_ci`, folds case and accents. PostgreSQL's
default collations are deterministic, equal only when the bytes are, and SQLite compares bytes: their columns keep the
default.

### Upgrading from 0.1

The tables of a 0.1 install on MySQL or MariaDB kept the connection's collation. `doctrine:migrations:diff` writes the
migration that brings the columns above to `utf8mb4_bin`; run it with `doctrine:migrations:migrate`. The change only
makes values more distinct, so no existing row breaks a key. Rows that a case-insensitive collation had already merged
stay merged; values written from the migration on are kept apart. On PostgreSQL and SQLite the diff is empty.
