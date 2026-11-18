# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `ext-mbstring` | any | An error kept to 255 characters |
| `doctrine/orm` | ^3.7 | The table of transitions |
| `psr/clock` | ^1.0 | What is due; the container's `clock` service, which FrameworkBundle registers once `symfony/clock` is installed |
| `symfony/dependency-injection`, `symfony/config`, `symfony/uid` | ^8.2 | The bundle class, its configuration, identifiers |
| `phil/resource` | ^0.1 | `ResourceReference`, the kind and identifier of a resource |
| `symfony/scheduler`, `symfony/messenger` | optional | Runs from Symfony Scheduler |
| `symfony/workflow` | optional | Transitions applied through a workflow |
| `symfony/console` | optional | `phil:scheduled-transitions:run` |

```sh
composer require phil/scheduled-transitions-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\ScheduledTransitionsBundle\PhilScheduledTransitionsBundle::class => ['all' => true],
];
```

```yaml
# config/packages/phil_scheduled_transitions.yaml
phil_scheduled_transitions:
    frequency: '1 minute'   # how often Symfony Scheduler runs what is due
    batch: 100              # transitions a run applies at most
    max_attempts: 3         # tries before a failing transition is given up
    claim_for: 600          # seconds a run holds a transition it applies
```

With DoctrineBundle, the bundle maps its entity; the application's migrations create `phil_scheduled_transition`:
`id`, `resource_kind`, `resource_id`, `transition` (64), `due_at` (UTC), `status` (`pending`, `done`, `failed`,
`cancelled`), `attempts`, `last_error` (255), `applied_at`, `claimed_until` (UTC, the claim of the run applying it),
indexed on status and moment, and on the resource.

## Collation of the keys

**Keys compare byte by byte**, as PHP compares them. The transitions of a resource are found by its key as
it was written: those scheduled for `Doc-A` are never listed or cancelled for `doc-a`.
`BinaryKeysListener` gives these fields the binary collation when the ORM loads their metadata on MySQL or MariaDB;
the mapping itself stays the same on every platform.

| Table | Columns in `utf8mb4_bin` on MySQL and MariaDB |
|---|---|
| `phil_scheduled_transition` | `id`, `resource_kind`, `resource_id`, `transition` |

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
SELECT resource_kind, resource_id, COUNT(*) AS row_count FROM phil_scheduled_transition
GROUP BY resource_kind, resource_id HAVING COUNT(DISTINCT CAST(resource_id AS BINARY)) > 1;
```

Each line is one key under several spellings. Keep them apart, or bring the rows to the spelling the application
uses with an `UPDATE` before the migration. A unique key cannot hold two such spellings under the old collation, so
the columns of a unique key need no check.
