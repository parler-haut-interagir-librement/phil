# Storage

Two stores keep the automations: `Definitions`, every version of each definition, and `RunStore`, the runs.

| Store | Use |
|---|---|
| `InMemoryDefinitions`, `InMemoryRunStore` | Tests, definitions written in code, a process that runs its automations at once |
| `Dbal\DbalDefinitions`, `Dbal\DbalRunStore` | A table each in a Doctrine DBAL connection, on PostgreSQL, MySQL, MariaDB or SQLite |

## The tables

```php
use Phil\Automations\Dbal\{DbalDefinitions, DbalRunStore};

$definitions = new DbalDefinitions($connection, $clock);            // table phil_automation_definition
$runs = new DbalRunStore($connection);                               // table phil_automation_run

$schema = $runs->configureSchema($definitions->configureSchema($schema));
```

`configureSchema()` adds a store's table to a schema that lacks it — the schema a migration builds, or the one the ORM
generates, from a listener of its `postGenerateSchema` event. A table name may be given as the last argument of each
store: lowercase letters, digits and `_`, at most 63 characters.

`phil_automation_definition` has one row per version: the automation's id, the version, the trigger type, whether it is
the enabled published version, the JSON `DefinitionWriter` writes, and when it was published. A version once kept
never changes: publishing it again with other steps is refused.

`phil_automation_run` has one row per run: its id, automation, version, subject, state, step, the instant it goes on,
its attempt, its payload and the action steps done as JSON, the reason of a failure, its revision, and when it started
and last changed. Instants are Unix timestamps. Every value is an identifier: the table holds no personal data the
application did not put in a subject or a payload.

## Creating once, saving once

A run is created by an insert that leaves a row of the same id alone — `ON CONFLICT DO NOTHING` on PostgreSQL,
`INSERT IGNORE` on MySQL and MariaDB, `INSERT OR IGNORE` on SQLite. It is saved by an `UPDATE … WHERE id = ? AND
revision = ?` that raises the revision: of two workers, one changes the row. Other platforms are refused at the first
creation.

## The purge

`RunStore::purge($before)` forgets the finished runs last changed before an instant; the runs waiting or running stay.
Call it from a scheduled task:

```php
$runs->purge($clock->now()->modify('-90 days'));
```
