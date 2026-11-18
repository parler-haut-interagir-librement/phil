# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `ext-mbstring` | any | Titles counted in characters |
| `doctrine/orm` | ^3.7 | The trash's table |
| `psr/clock`, `psr/event-dispatcher` | ^1.0 | When a resource is trashed, from the container's `clock` service, which FrameworkBundle registers once `symfony/clock` is installed; the events |
| `symfony/dependency-injection`, `symfony/config`, `symfony/uid` | ^8.2 | The bundle class, its configuration, identifiers |
| `phil/resource` | ^0.1 | `ResourceReference`, the kind and identifier of a resource |
| `symfony/console` | optional | `phil:trash:purge` |

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

With DoctrineBundle, the bundle maps its entity itself; the application's migrations create `phil_trash_item`:
`id`, `resource_key` (64), `resource_id` (255), `title` (255), `payload` (JSON), `parent` (`kind:id`, nullable),
`trashed_at`, `trashed_by` (nullable), `purge_after`, with indexes on the resource and on `purge_after`.

## Collation of the keys

**Keys compare byte by byte**, as PHP compares them. A trashed resource is found, restored and purged by
its key as it was written: the resource `Doc-A` is never restored or purged for `doc-a`.
`BinaryKeysListener` gives these fields the binary collation when the ORM loads their metadata on MySQL or MariaDB;
the mapping itself stays the same on every platform.

| Table | Columns in `utf8mb4_bin` on MySQL and MariaDB |
|---|---|
| `phil_trash_item` | `id`, `resource_key`, `resource_id`, `parent`, `trashed_by` |

On MySQL and MariaDB, these columns take the `utf8mb4` character set and the `utf8mb4_bin` collation, given the
connection's platform: the default collation of MySQL 8, `utf8mb4_0900_ai_ci`, folds case and accents. PostgreSQL's
default collations are deterministic, equal only when the bytes are, and SQLite compares bytes: their columns keep the
default.

### Upgrading from 0.1

The tables of a 0.1 install on MySQL or MariaDB kept the connection's collation. `doctrine:migrations:diff` writes
the migration that brings the columns above to `utf8mb4_bin`; run it with `doctrine:migrations:migrate`. On
PostgreSQL and SQLite the diff is empty.

The change only makes values more distinct, so no existing row breaks a unique key. It parts what the
case-insensitive collation joined: rows whose keys differ only by case, which a lookup by either spelling returned
together, are each found by their own spelling from the migration on, and a key the application looks up in
another case than it wrote stops matching. Before migrating, list the keys written in more than one case:

```sql
SELECT resource_key, resource_id, COUNT(*) AS row_count FROM phil_trash_item
GROUP BY resource_key, resource_id HAVING COUNT(DISTINCT CAST(resource_id AS BINARY)) > 1;
```

Each line is one key under several spellings. Keep them apart, or bring the rows to the spelling the application
uses with an `UPDATE` before the migration. A unique key cannot hold two such spellings under the old collation, so
the columns of a unique key need no check.
