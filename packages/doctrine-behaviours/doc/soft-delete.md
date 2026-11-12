# Soft delete

| Type | Kind | Role |
|---|---|---|
| `Contract\SoftDeletable` | interface | `deletedAt()`, `isDeleted()`, `markDeleted()`, `restore()` |
| `SoftDeletableEntity` | trait | The column `deleted_at`, `datetime_immutable`, nullable |
| `SoftDeleteListener` | listener: `onFlush` | Turns a removal into the deletion date |
| `SoftDeletedFilter` | SQL filter `phil_soft_deleted` | `deleted_at IS NULL` on every `SoftDeletable` entity |

```php
$manager->remove($category);
$manager->flush();                              // deleted_at = now; the row stays, the entity stays managed

$manager->getFilters()->disable(SoftDeletedFilter::NAME);
$category->restore();                           // deleted_at = null
$manager->flush();

$manager->remove($category);                    // already deleted: removed for good
```

The filter hides deleted rows from every query — `find()`, DQL, the collections of a relation loaded lazily — while it
is enabled. A foreign key `ON DELETE CASCADE` of the database never runs, since no row is deleted.

## Cascades

The listener keeps a removed `SoftDeletable` entity by persisting it again, and that persist cascades as the
associations say. So, for the entities a removal cascades to:

| The child is | The association cascades | The child |
|---|---|---|
| `SoftDeletable` | `persist` and `remove` | Is soft deleted too |
| not `SoftDeletable` | `persist` and `remove` | Stays: the parent's row stays, its children with it, ready for `restore()` |
| not `SoftDeletable` | `remove` only | Is deleted for good |

An application that wants the children gone with a soft-deleted parent removes them itself, or maps the association
with `remove` only.

## Relations to a deleted row

A to-one relation — `ManyToOne`, `OneToOne` — to a soft-deleted row is not hidden: the foreign key still holds its
identifier, and loading the related entity under the filter finds no row, so Doctrine throws an
`EntityNotFoundException`, `comment.article.title` for an article deleted. The ways out:

- set the foreign key to `null` when the target is soft deleted, if the relation is nullable;
- read such pages with the filter disabled, and show the target as deleted with `isDeleted()`;
- fetch the relation with a `LEFT JOIN` in the query: the filter applies to the join, and the relation is `null`.

A resource that should leave its table, with what it takes to bring it back, belongs in `phil/trash-bundle` instead.
