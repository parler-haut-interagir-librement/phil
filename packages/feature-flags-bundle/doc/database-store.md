# The database store

With `doctrine` enabled ([configuration.md](configuration.md#doctrine)), two tables hold what changes without a
deploy, on one DBAL connection.

| Service id | Class | Role |
|---|---|---|
| `phil_feature_flags.provider.doctrine` | `DoctrineFlagDefinitions` | A provider (`EnumerableFlagProvider`) after the configuration, and the definition store the admin writes to (`WritableFlagDefinitions`). |
| `phil_feature_flags.state_store.doctrine` | `DoctrineFlagStore` | The store of active states (`WritableFlagStore`) of any flag, from any provider. |
| `Phil\FeatureFlagsBundle\Infrastructure\Doctrine\FlagSchemaListener` | `FlagSchemaListener` | Adds the two tables to the ORM schema of that connection. |

## Tables

`phil_feature_flags_definition` (`FlagTables::DEFINITIONS`) — the flags created from the admin:

| Column | DBAL type | Null | Notes |
|---|---|---|---|
| `name` | `string(100)` | no | Primary key; the flag name. |
| `definition` | `json` | no | The definition in the shape of the configuration (`FlagDefinitionFormat::write()`), `active` included. |
| `revision` | `integer` | no | 1 at creation, incremented by each edit. |
| `updated_at` | `datetime_immutable` | no | From the configured clock. |

`phil_feature_flags_state` (`FlagTables::STATES`) — the active state of any flag once it has been toggled:

| Column | DBAL type | Null | Notes |
|---|---|---|---|
| `name` | `string(100)` | no | Primary key; the flag name. |
| `active` | `boolean` | no | The stored active state. |
| `updated_at` | `datetime_immutable` | no | From the configured clock. |

The definition JSON looks like:

```json
{
    "active": false,
    "rules": [{"attribute": "tenant", "members": ["tenant-a"]}],
    "experiment": {"attribute": "tenant", "variants": {"control": 50, "treatment": 50}},
    "description": "The new search.",
    "owner": "search-team",
    "kind": "permission",
    "expires_on": "2099-12-31"
}
```

Keys that hold nothing are left out. The `active` stored in the definition is the state the editor wrote at
creation (always `false`); the state the evaluator applies is the one in `phil_feature_flags_state`, which holds a
row for every created flag.

## Schema and migrations

`FlagSchemaListener` listens to the ORM's `postGenerateSchema` event for the configured connection (and checks the
entity manager's connection is that one), then adds both tables with `FlagTables::configure()`, leaving alone a
table that already exists. This is the pattern of the schema listeners Symfony's Doctrine bridge ships for its
session, lock, cache and Messenger stores. Consequences:

- `doctrine:migrations:diff` writes the migration that creates the tables;
- `doctrine:schema:validate` and `doctrine:schema:update` do not propose to drop them;
- the listener needs Doctrine DBAL 4.5 or later (`Schema::edit()`, `GenerateSchemaEventArgs::setSchema()`).

Without an ORM entity manager on that connection, create the tables from `FlagTables::configure(new Schema())`
([installation.md](installation.md#the-database-store-and-its-migration)).

## Reading once per request

Each store reads its whole table in **one query** on the first flag asked in a request and keeps the result for the
rest of the request. Both are tagged `kernel.reset`, so the next request (or the next message of a worker) reads
again. A toggle made by another process is therefore seen from the next request on, consistent with the
evaluator's own memoization ([flags-and-evaluation.md](flags-and-evaluation.md#once-per-request-and-context)).

Each write clears the store's cache, so the admin request that wrote sees its own change.

`DoctrineFlagDefinitions::findRevised()` does not use that cache: the editor and the admin read the current row and
revision directly.

## Conditional writes

Every write is one statement whose condition carries the race:

| Operation | Statement | Returns `false` when |
|---|---|---|
| `DoctrineFlagStore::compareAndSet()` | `UPDATE … SET active = ?, updated_at = ? WHERE name = ? AND active = ?` | The state is no longer the expected one, or the row is gone. |
| `DoctrineFlagStore::insert()` | `INSERT` | The primary key refuses it (`UniqueConstraintViolationException`). |
| `DoctrineFlagDefinitions::create()` | `INSERT` with revision 1 | The primary key refuses it. |
| `DoctrineFlagDefinitions::replace()` | `UPDATE … SET definition = ?, revision = revision + 1, updated_at = ? WHERE name = ? AND revision = ?` | The revision moved, or the row is gone. |

A change that lost a race writes nothing, and the toggle or editor turns the `false` into a conflict
([toggles-and-edits.md](toggles-and-edits.md)).

## Unreadable rows

**A row that no longer reads as a definition is unreadable, not absent** (invalid name, invalid JSON, a rule or
experiment the format refuses). `DoctrineFlagDefinitions` implements `DiagnosableFlagProvider`
([extension-points.md](extension-points.md#diagnosableflagprovider)):

- `find()` throws `InvalidValue` with the reason for that flag, so its evaluation is off, reason `ERROR`, code
  `PARSE_ERROR` (never `FLAG_NOT_FOUND`), and every other flag still evaluates; the evaluator never throws;
- `all()` leaves the row out;
- `unreadable()` lists every unreadable row as an `UnreadableFlag`, by the name it stores (which may itself be the
  problem) with the reason, sorted by name.

The catalog shows such a row apart, and the admin list and flag page show it with its reason
([admin.md](admin.md#screens)); it can be neither toggled nor edited from the admin, and its name cannot be created
again. `findRevised()` reads the row itself and throws the same `InvalidValue`.

JSON is decoded with a depth limit of 16. A revision column returned as a string of digits (some drivers do) is
accepted; any other non-integer revision is refused.

## Portability

The tables use portable DBAL types only. Booleans are read through the DBAL boolean type of the platform, so the
store behaves the same on SQLite, MySQL 8 and PostgreSQL, which the functional suite exercises
([testing.md](testing.md)).

## Retiring a flag

The bundle never deletes rows: there is no delete operation in the editor or the admin. Removing a flag created in
the database means deleting its row in `phil_feature_flags_definition` (and, to avoid a leftover state on a later
flag of the same name, in `phil_feature_flags_state`) with the application's own migration or tool. A state row
for a flag no provider declares is ignored by the evaluator.
