# Storage

A `Contract\TupleStore` keeps the tuples. It never returns a tuple that has expired by its own clock, bounds every read
by the limit it is given, and leaves the model's rules to `RelationWriter`.

| Method | Does |
|---|---|
| `update($delete, $write)` | Deletes then writes, all of it or nothing. |
| `matching($object, $relations, $subjects)` | The tuples on an object among some relations and subjects, exactly. |
| `subjects($object, $relation, $limit, $setsOnly)` | The tuples of a relation on an object, ordered by subject. |
| `objects($kind, $relation, $subject, $limit)` | The tuples of a relation given to a subject, on objects of a kind, ordered by object. |
| `forget($object)` | Deletes every tuple on an object and given to it: a deleted resource, an erased member. |
| `purgeExpired()` | Deletes the expired tuples. |

## In memory

`InMemoryTupleStore` keeps the tuples in an array, for tests and a single process, ordered as the database store
orders them. They are lost with the object.

## In a database

`Dbal\DbalTupleStore` keeps them in one table of a Doctrine DBAL connection, on PostgreSQL, MySQL, MariaDB or SQLite.

| Column | Type |
|---|---|
| `object_kind`, `relation`, `subject_kind`, `subject_relation` | `VARCHAR(64)`; `subject_relation` is empty for a subject that is one object or every object |
| `object_id`, `subject_id` | `VARCHAR(255)`; `*` for every object of a kind |
| `expires_at` | `BIGINT`, a Unix timestamp, or null |

| Index | Columns | Serves |
|---|---|---|
| Primary key | `object_kind`, `object_id`, `relation`, `subject_kind`, `subject_id`, `subject_relation` | A check: the tuples of an object. |
| `<table>_subject` | `subject_kind`, `subject_id`, `subject_relation`, `object_kind`, `relation` | A listing, and forgetting a member. |
| `<table>_expires_at` | `expires_at` | The purge. |

The table is `phil_relation_tuple`, or the constructor's third argument: up to 63 lowercase letters, digits or `_`,
not starting with a digit. **Identifiers compare byte by byte**: the text columns take `utf8mb4_bin` on MySQL and
MariaDB and the `C` collation on PostgreSQL, so `member:Ann` is never `member:ann`. On MySQL the primary key's 766
characters stay within the 3,072 bytes an index key may hold in `utf8mb4`.

An update is one transaction: each tuple deleted or written is deleted by its key, then each written one inserted.
`matching()` reads at most 256 relations and 256 subjects. Every read adds `expires_at IS NULL OR expires_at > ?`, the
clock's second.

## The schema

`configureSchema()` adds the table to a schema that lacks it. `Dbal\TupleSchemaListener`, on the ORM's
`postGenerateSchema` event, adds it to the schema the ORM generates for the store's connection, so that
`doctrine:migrations:diff` writes the migration that creates it.

## The purge

Expired tuples are never read, but stay in the table until `purgeExpired()` deletes them; a scheduled task calls it,
`phil/access-bundle`'s command `phil:access:purge` among them.

## Long lists

A check reads by the primary key; a listing by the subject index, at most the limit asked plus one per read. A long
list of documents shared with a member, or of members of a group, is never loaded whole.
