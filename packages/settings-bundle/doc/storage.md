# Storage

## The table

`phil_settings` holds one row per subject and key, the value encoded as JSON:

| Column | Type | Holds |
|---|---|---|
| `scope` | string(8) | `user` or `site` |
| `subject` | string(190) | The user's identifier, or `site` |
| `setting_key` | string(128) | The setting's key |
| `value` | text | The value, as JSON |
| `updated_at` | datetime | When it was saved |

The primary key is the scope, the subject and the key, so a subject's values are read by the start of the key. Only the
values that differ from their default have a row. A save removes the rows of the keys it touches and inserts the new
values in one transaction; of two saves of the same key at the same moment, the second fails on the primary key rather
than mixing them.

`phil_settings_migrations` holds the versions of the migrations applied:

| Column | Type | Holds |
|---|---|---|
| `version` | string(190) | The migration's version, the primary key |
| `applied_at` | datetime | When it was applied |

A migration writes its changes and the row of its version in one transaction: of two runs of the same migration at
once, the second fails on the primary key and changes nothing. It reads every value of a key, across subjects, without
an index of its own: it runs at a deployment, not on a page.

`Infrastructure\Doctrine\SettingTables::configure()` adds the tables a DBAL schema lacks, and the bundle adds them to
the schema the ORM generates for the configured connection.

## Collation of the keys

**Keys compare byte by byte**, as PHP compares them. The subject is the user's identifier, and a setting is read by
its scope, its subject and its key: the values of the user `Ann` are never read for `ann` or `ánn`.

| Table | Columns in `utf8mb4_bin` on MySQL and MariaDB |
|---|---|
| `phil_settings` | `scope`, `subject`, `setting_key` |
| `phil_settings_migrations` | `version` |

On MySQL and MariaDB, these columns take the `utf8mb4` character set and the `utf8mb4_bin` collation, given the
connection's platform: the default collation of MySQL 8, `utf8mb4_0900_ai_ci`, folds case and accents. PostgreSQL's
default collations are deterministic, equal only when the bytes are, and SQLite compares bytes: their columns keep the
default.

### Upgrading from 0.1

The tables of a 0.1 install on MySQL or MariaDB kept the connection's collation. `doctrine:migrations:diff` writes the
migration that brings the columns above to `utf8mb4_bin`; run it with `doctrine:migrations:migrate`. The change only
makes values more distinct, so no existing row breaks a key. Rows that a case-insensitive collation had already merged
stay merged; values written from the migration on are kept apart. On PostgreSQL and SQLite the diff is empty.

## Reading once per request

`Settings` reads all the values of a subject in one query the first time one is asked, then keeps them for the request,
each decoded once. A save forgets the subject's values, which are read again when next asked. The container resets the
service between two requests of a worker — FrankenPHP, RoadRunner, a Messenger consumer — through `kernel.reset`, so no
value outlives its request.

## For tests

`store: memory` keeps the values, and the versions of the migrations applied, in memory for as long as the container
lives.
