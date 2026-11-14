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

## Reading once per request

`Settings` reads all the values of a subject in one query the first time one is asked, then keeps them for the request,
each decoded once. A save forgets the subject's values, which are read again when next asked. The container resets the
service between two requests of a worker — FrankenPHP, RoadRunner, a Messenger consumer — through `kernel.reset`, so no
value outlives its request.

## For tests

`store: memory` keeps the values, and the versions of the migrations applied, in memory for as long as the container
lives.
