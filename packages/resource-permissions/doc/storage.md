# Storage

A `Contract\TupleStore` keeps the tuples. It never returns a tuple that has expired by its own clock, bounds every read
by the limit it is given, and leaves the model's rules to `RelationWriter`. Each call acts in the partition its
`Contract\TupleScope` names, and in no other: see [Partitions](partitions.md).

| Method | Does |
|---|---|
| `update($delete, $write)` | Deletes then writes, all of it or nothing. |
| `matching($object, $relations, $subjects)` | The tuples on an object among some relations and subjects, exactly. |
| `subjects($object, $relation, $limit, $setsOnly)` | The tuples of a relation on an object, ordered by subject. |
| `objects($kind, $relation, $subject, $limit)` | The tuples of a relation given to a subject, on objects of a kind, ordered by object. |
| `forget($object)` | Deletes every tuple on an object and given to it: a deleted resource, an erased member. |
| `purgeExpired()` | Deletes the expired tuples of the partition. |

## In memory

`InMemoryTupleStore` keeps the tuples in an array, for tests and a single process, ordered as the database store
orders them, each partition apart. They are lost with the object.

## In a database

`Dbal\DbalTupleStore` keeps them in one table of a Doctrine DBAL connection, on PostgreSQL, MySQL, MariaDB or SQLite.

| Column | Type |
|---|---|
| `scope` | `VARCHAR(128)`, the partition; empty, its default, for the default partition |
| `object_kind`, `relation`, `subject_kind`, `subject_relation` | `VARCHAR(64)`; `subject_relation` is empty for a subject that is one object or every object |
| `object_id`, `subject_id` | `VARCHAR(255)`; `*` for every object of a kind |
| `expires_at` | `BIGINT`, a Unix timestamp, or null |

| Index | Columns | Serves |
|---|---|---|
| Primary key | `scope`, `object_kind`, `object_id`, `relation`, `subject_kind`, `subject_id`, `subject_relation` | A check: the tuples of an object. |
| `<table>_subject` | `scope`, `subject_kind`, `subject_id`, `subject_relation`, `object_kind`, `relation` | A listing, and forgetting a member. |
| `<table>_expires_at` | `scope`, `expires_at` | The purge. |

The table is `phil_relation_tuple`, or the constructor's third argument: up to 63 lowercase letters, digits or `_`,
not starting with a digit. The fourth argument, `scope`, is the `TupleScope`; `SinglePartition` by default.
**Identifiers compare byte by byte**: the text columns take a binary collation — `utf8mb4_bin` on MySQL and MariaDB,
`ascii_bin` for the names of kinds and relations, which their rule keeps in lowercase ASCII — and the `C` collation on
PostgreSQL, so `member:Ann` is never `member:ann`. On MySQL the primary key holds at most 2,808 bytes, within the
3,072 an index key may hold.

Every statement starts with `scope = ?`, the partition of the call. An update is one transaction: each tuple deleted
or written is deleted by its key, then each written one inserted. `matching()` reads at most 256 relations and 256
subjects. Every read adds `expires_at IS NULL OR expires_at > ?`, the clock's second.

## The schema

`configureSchema()` adds the table to a schema that lacks it. `Dbal\TupleSchemaListener`, on the ORM's
`postGenerateSchema` event, adds it to the schema the ORM generates for the store's connection, so that
`doctrine:migrations:diff` writes the migration that creates it, or that brings an older table to this version.
Without the ORM, `schemaUpdate()` gives the statements that create the table, alter an older one, or nothing when it is
current. See [Upgrading from 0.1](partitions.md#upgrading-from-01).

## The purge

Expired tuples are never read, but stay in the table until `purgeExpired()` deletes them, in the partition of the
call; a scheduled task calls it for each partition, `phil/access-bundle`'s command `phil:access:purge` among them.

## Long lists

A check reads by the primary key; a listing by the subject index, at most the limit asked plus one per read. A long
list of documents shared with a member, or of members of a group, is never loaded whole.
