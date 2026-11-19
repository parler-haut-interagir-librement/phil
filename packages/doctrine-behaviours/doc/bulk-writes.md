# Bulk writes

The behaviours are Doctrine listeners: they see what goes through the unit of work of an entity manager they are
registered on, and nothing else. A write that goes around it keeps no timestamp, no blame, no slug, no version, and is
never soft deleted; an erasure that goes around it must reach every store itself.

## What no listener sees

| Write | What the behaviours do |
|---|---|
| A DQL `UPDATE` or `DELETE` | Nothing: `updated_at` and `updated_by` keep their values, a `DELETE` deletes the rows for good, soft-deletable or not, and no version forks |
| A DBAL statement, `executeStatement()`, `insert()`, `update()`, `delete()` | Nothing, for the same reason |
| A migration | Nothing: the rows it writes or deletes are left as it leaves them |
| An entity manager built outside the container, or a second entity manager | Only the listeners registered on that manager run; a manager with none writes as DBAL does |
| A database trigger, a foreign key `ON DELETE CASCADE` or `SET NULL` | Nothing: the database changes the rows behind Doctrine |

So a DQL `DELETE` of soft-deletable entities removes their rows, filter or not, and a DQL `UPDATE` of a frozen
`Versionable` record changes it in place, its snapshot unforked. Load and flush the entities when the behaviours must
apply; write in bulk when they must not, and keep what they would have written by hand.

`BlameEraser`, `OwnerEraser` and `VersionEraser` write in bulk on purpose: an erasure is not an update of the entities,
and no listener stamps the rows they change. Given a `ManagerRegistry`, they reach every entity manager, each table
once per connection, so a second manager is not left out; a manager the registry does not give, or a store that is no
table of a mapped class, is the application's to erase.

## `postFlush` is not after the commit

`postFlush` runs once the unit of work has written its statements, inside the transaction of an outer
`wrapInTransaction()` or `beginTransaction()` if there is one. A listener that sends a message, indexes a document or
calls a service there acts on rows that may still be rolled back, and a failure after it leaves the outside world ahead
of the database. Work that must follow the commit goes through an outbox written in the same transaction, or a
Messenger message dispatched once the transaction ends. `TreeListener` only uses `postFlush` to forget the moves of
the flush.

## The blame copies a name

`BlameListener` writes in `updated_by` the name the resolver gives on every update, and in `created_by` on every insert.
With Symfony Security that name is `getUserIdentifier()` unless the bundle is told otherwise, often an e-mail address.
An erasure that loads and flushes entities, an eraser of the application's own for one, writes that name again after
`BlameEraser` replaced it: run it where nobody is authenticated, a worker or a console command, or inside
`BlameSuspension::during()` — see [Timestamps and blame](timestamps-and-blame.md#the-blame-during-an-erasure).

## The slug copies its source

`SlugListener` makes a slug once, from `slugSource()`, and keeps it. A slug drawn from a person's name, `ada-lovelace`,
is personal information that outlives a change of the name, and the erasure of the name: the slug is in the URL, in the
logs and in the links others keep. Draw the slug of a person's record from something that names nobody, an identifier
or a chosen handle, or give the record a new slug with `assignSlug()` as its name is erased.

## Soft delete keeps the row

A first `remove()` of a `SoftDeletable` entity keeps its whole row, with `deleted_at` set: an erasure that removes such
an entity has erased nothing. `SoftDeletes::purge()` deletes the row — see [Soft delete](soft-delete.md).
