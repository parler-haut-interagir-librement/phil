# Storage

`Contract\ActivationStore` keeps the activations. Two stores come with the library.

| Store | For |
|---|---|
| `InMemoryActivationStore` | Tests and single runs; `reset()` empties it |
| `Dbal\DbalActivationStore` | A Doctrine DBAL connection on PostgreSQL, MySQL, MariaDB or SQLite |

## The table

`Dbal\IncidentTables::ACTIVATIONS`, `phil_incident_activation`, holds one row per activation:

| Column | Type | Holds |
|---|---|---|
| `id` | `CHAR(32)`, primary key | The random identifier |
| `preset` | `VARCHAR(64)` | The name of the preset |
| `restrictions` | `TEXT` | The names of the restrictions, joined by commas |
| `started_at`, `ends_at` | `datetime_utc_immutable` | The start and the end |
| `started_by` | `VARCHAR(190)`, null | The actor who started it |
| `reason` | `TEXT`, null | Why |
| `lifted_at` | `datetime_utc_immutable`, null | When it was lifted, or its end when it expired |
| `lifted_by` | `VARCHAR(190)`, null | The actor who lifted it |
| `lift_reason` | `VARCHAR(16)`, null | `manual` or `expired` |

The moments are written and read in UTC, so that two servers in different time zones read the same end. The index
`idx_phil_incident_in_force` on (`lifted_at`, `ends_at`) serves the question of every check, and
`idx_phil_incident_started` the journal.

`IncidentTables::configure($schema)` adds the table to a schema that lacks it; `Dbal\IncidentSchemaListener`, on the
ORM's `postGenerateSchema` event, adds it to the schema the ORM generates for the store's connection, so that
`doctrine:migrations:diff` writes the migration that creates it.

## Concurrency

`lift()` and `extend()` are one `UPDATE … WHERE lifted_at IS NULL` each, and say whether they changed the row. Two
workers lifting the same incident see one of them succeed; `IncidentMode` sends the event only for it.

A row that no longer reads — edited by hand into a restriction, an actor or a reason the library refuses — is left out
of every answer rather than failing every check.

## Personal data

The actors are personal data. `ofActor($actor)` gives the activations an actor started or lifted, for an access
request; `forgetActor($actor)` removes the actor from them, and the activations stay in the journal without a name.
`phil/moderation-bundle` answers both through the contracts of `phil/privacy-bundle`.
